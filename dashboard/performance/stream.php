<?php
/**
 * XAMPP Dashboard - realtime PC performance stream.
 *
 * Server-Sent Events endpoint consumed by /dashboard/performance/.
 * Reads Windows performance counters through WMI (PHP's com_dotnet extension).
 * Counters are registered once on an SWbemRefresher, so each sample costs a few
 * milliseconds instead of a full WMI query.
 *
 *   GET stream.php?interval=1   stream samples every 1-10 seconds
 *   GET stream.php?probe=1      JSON health check (used by the page when the stream fails)
 */

declare(strict_types=1);

const ALLOW_REMOTE   = false; // true = allow other devices on your network to view this machine's stats
const STREAM_SECONDS = 600;   // the browser reconnects automatically when a stream ends
const PROC_LIMIT     = 15;    // top N processes sent by CPU and by memory
const CODEPAGE_UTF8  = 65001; // same value as com_dotnet's CP_UTF8

const XAMPP_SERVICES = [
    'httpd'           => ['label' => 'Apache HTTP Server', 'port' => '80 / 443', 'core' => true],
    'mysqld'          => ['label' => 'MariaDB (MySQL)',    'port' => '3306',     'core' => true],
    'FileZillaServer' => ['label' => 'FileZilla FTP',      'port' => '21',       'core' => false],
    'mercury'         => ['label' => 'Mercury Mail',       'port' => '25',       'core' => false],
];

ini_set('display_errors', '0'); // stray warnings would corrupt the event stream

// The stream exposes process names, so keep it local unless explicitly opened up.
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!ALLOW_REMOTE && !in_array($remote, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('The performance stream only serves localhost. Set ALLOW_REMOTE in dashboard/performance/stream.php to change this.');
}

if (isset($_GET['probe'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'ok'      => class_exists('COM'),
        'com'     => class_exists('COM'),
        'php'     => PHP_VERSION,
        'ini'     => php_ini_loaded_file() ?: null,
        'message' => class_exists('COM') ? 'Stream endpoint is reachable.' : "PHP's COM extension (com_dotnet) is not loaded.",
    ]);
    exit;
}

/* ------------------------------------------------------------------ */
/* Event-stream plumbing                                               */
/* ------------------------------------------------------------------ */

@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) {
    @ob_end_clean();
}
ignore_user_abort(false);
set_time_limit(STREAM_SECONDS + 60);

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, no-transform');
header('X-Accel-Buffering: no');

function send(string $event, array $data): void
{
    $json = json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
    );
    echo "event: {$event}\ndata: {$json}\n\n";
    flush();
}

echo "retry: 3000\n\n";
flush();

$iniPath = php_ini_loaded_file() ?: 'C:\\xampp\\php\\php.ini';

if (!class_exists('COM')) {
    send('fatal', [
        'code'    => 'com_missing',
        'title'   => 'Enable PHP\'s COM extension to read live counters',
        'message' => "Windows performance counters are read through WMI, which needs PHP's built-in com_dotnet extension. It isn't loaded in Apache yet.",
        'steps'   => [
            "Open {$iniPath} and make sure the line extension=com_dotnet is present without a leading semicolon.",
            'Restart Apache from the XAMPP Control Panel (Stop, then Start).',
            'Reload this page.',
        ],
    ]);
    exit;
}

/* ------------------------------------------------------------------ */
/* WMI helpers                                                         */
/* ------------------------------------------------------------------ */

/** Read a numeric COM property; missing or null properties become 0. */
function num($obj, string $prop): float
{
    try {
        $v = $obj->$prop;
    } catch (Throwable $e) {
        return 0.0;
    }
    return is_numeric($v) ? (float) $v : 0.0;
}

/** Read a string COM property; missing or null properties become ''. */
function str($obj, string $prop): string
{
    try {
        $v = $obj->$prop;
    } catch (Throwable $e) {
        return '';
    }
    return $v === null ? '' : trim((string) $v);
}

function pct(float $v): float
{
    return round(max(0.0, min(100.0, $v)), 1);
}

/** Run a one-off WQL query and return the rows as an array. */
function rows($wmi, string $wql): array
{
    $out = [];
    try {
        foreach ($wmi->ExecQuery($wql) as $o) {
            $out[] = $o;
        }
    } catch (Throwable $e) {
        // class not available on this Windows build
    }
    return $out;
}

/** Current objects of a refreshable enum (after Refresh() was called). */
function items($item): array
{
    if ($item === null) {
        return [];
    }
    $out = [];
    try {
        foreach ($item->ObjectSet as $o) {
            $out[] = $o;
        }
    } catch (Throwable $e) {
    }
    return $out;
}

function addEnum($refresher, $wmi, string $class)
{
    try {
        return $refresher->AddEnum($wmi, $class);
    } catch (Throwable $e) {
        return null;
    }
}

function refresh($refresher): void
{
    try {
        $refresher->Refresh();
    } catch (Throwable $e) {
    }
}

/* ------------------------------------------------------------------ */
/* Samplers                                                            */
/* ------------------------------------------------------------------ */

function systemInfo($wmi): array
{
    $info = [
        'host'     => gethostname() ?: 'localhost',
        'os'       => 'Windows',
        'build'    => '',
        'arch'     => '',
        'cpu'      => ['name' => 'Processor', 'cores' => 0, 'threads' => 0, 'sockets' => 0, 'baseMHz' => 0],
        'memTotal' => 0.0,
        'gpus'     => [],
        'php'      => PHP_VERSION,
        'server'   => $_SERVER['SERVER_SOFTWARE'] ?? '',
    ];

    foreach (rows($wmi, 'SELECT Name, NumberOfCores, NumberOfLogicalProcessors, MaxClockSpeed FROM Win32_Processor') as $p) {
        $info['cpu']['name']     = preg_replace('/\s+/', ' ', str($p, 'Name'));
        $info['cpu']['cores']   += (int) num($p, 'NumberOfCores');
        $info['cpu']['threads'] += (int) num($p, 'NumberOfLogicalProcessors');
        $info['cpu']['baseMHz']  = max($info['cpu']['baseMHz'], (int) num($p, 'MaxClockSpeed'));
        $info['cpu']['sockets']++;
    }

    foreach (rows($wmi, 'SELECT Caption, Version, OSArchitecture, TotalVisibleMemorySize, CSName FROM Win32_OperatingSystem') as $o) {
        $info['os']       = preg_replace('/^Microsoft\s+/i', '', str($o, 'Caption'));
        $info['build']    = str($o, 'Version');
        $info['arch']     = str($o, 'OSArchitecture');
        $info['memTotal'] = num($o, 'TotalVisibleMemorySize') * 1024;
        $info['host']     = str($o, 'CSName') ?: $info['host'];
    }

    foreach (rows($wmi, 'SELECT Name FROM Win32_VideoController') as $g) {
        $name = str($g, 'Name');
        if ($name !== '') {
            $info['gpus'][] = $name;
        }
    }

    return $info;
}

function sampleCpu(array $cpuRows, array $infoRows, int $baseMHz): array
{
    $total = 0.0;
    $cores = [];
    foreach ($cpuRows as $o) {
        $name = str($o, 'Name');
        $v    = pct(num($o, 'PercentProcessorTime'));
        if ($name === '_Total') {
            $total = $v;
        } else {
            $cores[$name] = $v;
        }
    }
    uksort($cores, 'strnatcmp');

    // Effective clock = nominal frequency x "% processor performance" (exceeds 100% under turbo).
    $mhz = $baseMHz;
    foreach ($infoRows as $o) {
        if (str($o, 'Name') === '_Total') {
            $freq = num($o, 'ProcessorFrequency') ?: $baseMHz;
            $perf = num($o, 'PercentProcessorPerformance');
            $mhz  = $perf > 0 ? $freq * $perf / 100 : $freq;
        }
    }

    return ['total' => $total, 'cores' => array_values($cores), 'mhz' => (int) round($mhz)];
}

function sampleMemory(array $rows, float $total): array
{
    $o = $rows[0] ?? null;
    if ($o === null || $total <= 0) {
        return ['total' => $total];
    }
    $avail   = num($o, 'AvailableBytes');
    $standby = num($o, 'StandbyCacheCoreBytes') + num($o, 'StandbyCacheNormalPriorityBytes') + num($o, 'StandbyCacheReserveBytes');

    return [
        'total'        => $total,
        'used'         => max(0.0, $total - $avail),
        'avail'        => $avail,
        'standby'      => $standby,
        'modified'     => num($o, 'ModifiedPageListBytes'),
        'free'         => num($o, 'FreeAndZeroPageListBytes'),
        'committed'    => num($o, 'CommittedBytes'),
        'commitLimit'  => num($o, 'CommitLimit'),
        'pagedPool'    => num($o, 'PoolPagedBytes'),
        'nonPagedPool' => num($o, 'PoolNonpagedBytes'),
        'hardFaults'   => num($o, 'PagesPersec'),
    ];
}

function sampleSystem(array $rows): array
{
    $o = $rows[0] ?? null;
    if ($o === null) {
        return [];
    }
    return [
        'processes'   => (int) num($o, 'Processes'),
        'threads'     => (int) num($o, 'Threads'),
        'uptime'      => (int) num($o, 'SystemUpTime'),
        'queue'       => (int) num($o, 'ProcessorQueueLength'),
        'ctxSwitches' => (int) num($o, 'ContextSwitchesPersec'),
    ];
}

function sampleDisk(array $rows): array
{
    $disks  = [];
    $read   = 0.0;
    $write  = 0.0;
    $active = 0.0;
    foreach ($rows as $o) {
        $name = str($o, 'Name');
        $r    = num($o, 'DiskReadBytesPersec');
        $w    = num($o, 'DiskWriteBytesPersec');
        if ($name === '_Total') {
            $read  = $r;
            $write = $w;
            continue;
        }
        $a = pct(100 - num($o, 'PercentIdleTime'));
        // "0 C: D:" -> index 0, volumes "C: D:"
        preg_match('/^(\d+)\s*(.*)$/', $name, $m);
        $disks[] = [
            'index'   => isset($m[1]) ? (int) $m[1] : count($disks),
            'volumes' => isset($m[2]) ? trim($m[2]) : $name,
            'active'  => $a,
            'read'    => $r,
            'write'   => $w,
            'queue'   => num($o, 'CurrentDiskQueueLength'),
        ];
        $active = max($active, $a);
    }
    return ['active' => $active, 'read' => $read, 'write' => $write, 'disks' => $disks];
}

function sampleNetwork(array $rows): array
{
    $adapters = [];
    $rx       = 0.0;
    $tx       = 0.0;
    foreach ($rows as $o) {
        $name = str($o, 'Name');
        if (preg_match('/isatap|teredo|6to4|loopback|pseudo/i', $name)) {
            continue;
        }
        $r = num($o, 'BytesReceivedPersec');
        $t = num($o, 'BytesSentPersec');
        $adapters[] = [
            'name' => strtr($name, ['[' => '(', ']' => ')']),
            'rx'   => $r,
            'tx'   => $t,
            'bw'   => num($o, 'CurrentBandwidth'), // bits per second
        ];
        $rx += $r;
        $tx += $t;
    }
    return ['rx' => $rx, 'tx' => $tx, 'adapters' => $adapters];
}

function sampleProcesses(array $rows, int $logical): array
{
    $all     = [];
    $handles = 0;
    foreach ($rows as $o) {
        $name = str($o, 'Name');
        if ($name === '_Total') {
            $handles = (int) num($o, 'HandleCount');
            continue;
        }
        if ($name === 'Idle' || $name === '') {
            continue;
        }
        $all[] = [
            'pid'     => (int) num($o, 'IDProcess'),
            'name'    => preg_replace('/#\d+$/', '', $name),
            // the counter is "% of one core"; normalise to % of the whole machine
            'cpu'     => round(num($o, 'PercentProcessorTime') / max(1, $logical), 1),
            'mem'     => num($o, 'WorkingSetPrivate'),
            'threads' => (int) num($o, 'ThreadCount'),
        ];
    }

    $services = [];
    foreach (XAMPP_SERVICES as $exe => $meta) {
        $svc = ['key' => $exe, 'label' => $meta['label'], 'port' => $meta['port'], 'core' => $meta['core'],
                'running' => false, 'count' => 0, 'cpu' => 0.0, 'mem' => 0.0, 'pids' => []];
        foreach ($all as $p) {
            if (strcasecmp($p['name'], $exe) === 0) {
                $svc['running'] = true;
                $svc['count']++;
                $svc['cpu']    += $p['cpu'];
                $svc['mem']    += $p['mem'];
                $svc['pids'][]  = $p['pid'];
            }
        }
        $svc['cpu'] = round($svc['cpu'], 1);
        if ($svc['core'] || $svc['running']) {
            $services[] = $svc;
        }
    }

    $top = [];
    usort($all, static fn ($a, $b) => $b['cpu'] <=> $a['cpu']);
    foreach (array_slice($all, 0, PROC_LIMIT) as $p) {
        $top[$p['pid']] = $p;
    }
    usort($all, static fn ($a, $b) => $b['mem'] <=> $a['mem']);
    foreach (array_slice($all, 0, PROC_LIMIT) as $p) {
        $top[$p['pid']] = $p;
    }

    return ['list' => array_values($top), 'count' => count($all), 'handles' => $handles, 'services' => $services];
}

function sampleGpu(array $engineRows, array $memoryRows): ?array
{
    if (!$engineRows) {
        return null;
    }

    // Instances are per process + engine; sum per engine, then take the busiest engine
    // (the same way Task Manager reports GPU utilisation).
    $engines = [];
    foreach ($engineRows as $o) {
        if (!preg_match('/luid_(0x[0-9a-f]+_0x[0-9a-f]+)_phys_(\d+)_eng_(\d+)_engtype_(.*)$/i', str($o, 'Name'), $m)) {
            continue;
        }
        $key = "{$m[1]}|{$m[2]}|{$m[3]}";
        if (!isset($engines[$key])) {
            $type = preg_replace('/_\d+$/', '', $m[4]);
            $engines[$key] = ['luid' => $m[1], 'type' => $type !== '' ? $type : 'Other', 'util' => 0.0];
        }
        $engines[$key]['util'] += num($o, 'UtilizationPercentage');
    }

    $util     = 0.0;
    $types    = [];
    $adapters = [];
    foreach ($engines as $e) {
        $u = pct($e['util']);
        $util = max($util, $u);
        $types[$e['type']] = max($types[$e['type']] ?? 0.0, $u);
        $adapters[$e['luid']] ??= ['util' => 0.0, 'dedicated' => 0.0, 'shared' => 0.0];
        $adapters[$e['luid']]['util'] = max($adapters[$e['luid']]['util'], $u);
    }

    $dedicated = 0.0;
    $shared    = 0.0;
    foreach ($memoryRows as $o) {
        if (!preg_match('/luid_(0x[0-9a-f]+_0x[0-9a-f]+)/i', str($o, 'Name'), $m) || !isset($adapters[$m[1]])) {
            continue;
        }
        $adapters[$m[1]]['dedicated'] += num($o, 'DedicatedUsage');
        $adapters[$m[1]]['shared']    += num($o, 'SharedUsage');
        $dedicated += num($o, 'DedicatedUsage');
        $shared    += num($o, 'SharedUsage');
    }

    arsort($types);
    ksort($adapters);

    return [
        'util'      => $util,
        'types'     => $types,
        'dedicated' => $dedicated,
        'shared'    => $shared,
        'adapters'  => array_values($adapters),
    ];
}

function sampleDrives($wmi): array
{
    $out = [];
    foreach (rows($wmi, 'SELECT DeviceID, VolumeName, FileSystem, Size, FreeSpace FROM Win32_LogicalDisk WHERE DriveType = 3') as $d) {
        $size = num($d, 'Size');
        if ($size <= 0) {
            continue;
        }
        $out[] = [
            'id'    => str($d, 'DeviceID'),
            'label' => str($d, 'VolumeName'),
            'fs'    => str($d, 'FileSystem'),
            'size'  => $size,
            'free'  => num($d, 'FreeSpace'),
        ];
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/* Connect and stream                                                  */
/* ------------------------------------------------------------------ */

try {
    $locator = new COM('WbemScripting.SWbemLocator', null, CODEPAGE_UTF8);
    $wmi     = $locator->ConnectServer('.', 'root\\cimv2');
} catch (Throwable $e) {
    send('fatal', [
        'code'    => 'wmi_unavailable',
        'title'   => 'Could not connect to Windows Management Instrumentation',
        'message' => 'WMI refused the connection: ' . trim($e->getMessage()),
        'steps'   => [
            'Make sure the "Windows Management Instrumentation" service is running (services.msc).',
            'Restart Apache from the XAMPP Control Panel and reload this page.',
        ],
    ]);
    exit;
}

$interval = max(1, min(10, (int) ($_GET['interval'] ?? 1)));
$info     = systemInfo($wmi);
$info['interval'] = $interval;
$info['drives']   = sampleDrives($wmi);

$fast = new COM('WbemScripting.SWbemRefresher', null, CODEPAGE_UTF8);
$enum = [
    'cpu'     => addEnum($fast, $wmi, 'Win32_PerfFormattedData_PerfOS_Processor'),
    'cpuInfo' => addEnum($fast, $wmi, 'Win32_PerfFormattedData_Counters_ProcessorInformation'),
    'mem'     => addEnum($fast, $wmi, 'Win32_PerfFormattedData_PerfOS_Memory'),
    'sys'     => addEnum($fast, $wmi, 'Win32_PerfFormattedData_PerfOS_System'),
    'disk'    => addEnum($fast, $wmi, 'Win32_PerfFormattedData_PerfDisk_PhysicalDisk'),
    'net'     => addEnum($fast, $wmi, 'Win32_PerfFormattedData_Tcpip_NetworkInterface'),
];

// Slower collections get their own refreshers so they can run on a slower cadence.
$procRef  = new COM('WbemScripting.SWbemRefresher', null, CODEPAGE_UTF8);
$procEnum = addEnum($procRef, $wmi, 'Win32_PerfFormattedData_PerfProc_Process');

$gpuRef     = new COM('WbemScripting.SWbemRefresher', null, CODEPAGE_UTF8);
$gpuEngines = addEnum($gpuRef, $wmi, 'Win32_PerfFormattedData_GPUPerformanceCounters_GPUEngine');
$gpuMemory  = addEnum($gpuRef, $wmi, 'Win32_PerfFormattedData_GPUPerformanceCounters_GPUAdapterMemory');

// Formatted counters need two raw samples; prime every refresher once.
refresh($fast);
refresh($procRef);
if ($gpuEngines !== null) {
    refresh($gpuRef);
}

$info['hasGpu'] = $gpuEngines !== null;
send('info', $info);

$logical    = max(1, $info['cpu']['threads'] ?: 1);
$procEvery  = max(1, (int) ceil(3 / $interval));
$gpuEvery   = max(1, (int) ceil(2 / $interval));
$driveEvery = max(1, (int) ceil(30 / $interval));

$deadline = microtime(true) + STREAM_SECONDS;
$nextAt   = microtime(true) + 0.75;
$tick     = 0;
$lastGpuTick = -100;

while (true) {
    $now = microtime(true);
    if ($now >= $deadline) {
        break;
    }
    if ($nextAt > $now) {
        usleep((int) (($nextAt - $now) * 1e6));
    }
    $started = microtime(true);
    $nextAt  = max($nextAt + $interval, $started + 0.25); // never burst after a slow sample

    refresh($fast);
    $payload = [
        't'    => (int) round($started * 1000),
        'cpu'  => sampleCpu(items($enum['cpu']), items($enum['cpuInfo']), (int) $info['cpu']['baseMHz']),
        'mem'  => sampleMemory(items($enum['mem']), (float) $info['memTotal']),
        'sys'  => sampleSystem(items($enum['sys'])),
        'disk' => sampleDisk(items($enum['disk'])),
        'net'  => sampleNetwork(items($enum['net'])),
    ];

    // Processes and GPU are the expensive collections; keep them on different ticks.
    $isProcTick = $tick % $procEvery === 0;
    if ($isProcTick) {
        refresh($procRef);
        $payload['procs'] = sampleProcesses(items($procEnum), $logical);
    }
    if ($gpuEngines !== null && ($procEvery === 1 || (!$isProcTick && $tick - $lastGpuTick >= $gpuEvery))) {
        refresh($gpuRef);
        $payload['gpu'] = sampleGpu(items($gpuEngines), items($gpuMemory));
        $lastGpuTick    = $tick;
    }
    if ($tick > 0 && $tick % $driveEvery === 0) {
        $payload['drives'] = sampleDrives($wmi);
    }

    $payload['cost'] = (int) round((microtime(true) - $started) * 1000);
    send('tick', $payload);

    if (connection_aborted()) {
        break;
    }
    $tick++;
}

send('bye', ['reason' => 'lifetime']);
