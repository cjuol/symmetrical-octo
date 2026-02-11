<?php

declare(strict_types=1);

use Cjuol\StatGuard\QuantileEngine;
use Cjuol\StatGuard\RobustStats;
use MathPHP\Statistics\Average;
use MathPHP\Statistics\Descriptive;

require __DIR__ . '/../vendor/autoload.php';

/**
 * CONFIGURACIÓN DEL BENCHMARK
 */
$sizes = [1000, 10000, 100000];
$probs = [0.5, 0.75];
$format = $argv[1] ?? 'table';

mt_srand(42);

// --- 1. HELPERS DE DATOS Y SISTEMA ---

/** Genera un array de floats aleatorios */
function generateDataset(int $size): array {
    $data = [];
    for ($i = 0; $i < $size; $i++) {
        $data[] = mt_rand(0, 1000000) / 1000;
    }
    return $data;
}

/** Limpia ciclos de memoria antes de cada test para asegurar mediciones limpias */
function resetMemory(): void {
    gc_collect_cycles();
}

// --- 2. ALGORITMOS DE REFERENCIA (MANUALES) ---

function manualMedian(array $data): float {
    sort($data, SORT_NUMERIC);
    $n = count($data);
    $mid = intdiv($n, 2);
    return ($n % 2 === 0) 
        ? ((float)$data[$mid - 1] + (float)$data[$mid]) / 2.0 
        : (float)$data[$mid];
}

function manualQuantileType7(array $data, float $p): float {
    sort($data, SORT_NUMERIC);
    $n = count($data);
    $p = max(0.0, min(1.0, $p));
    $h = 1.0 + ($n - 1.0) * $p;
    $k = (int) floor($h);
    $d = $h - $k;
    
    if ($k <= 1) return (float)$data[0];
    if ($k >= $n) return (float)$data[$n - 1];
    
    return (float)$data[$k - 1] + $d * ((float)$data[$k] - (float)$data[$k - 1]);
}

// --- 3. INTEGRACIÓN CON R ---

/** Ejecuta el benchmark en R y devuelve los tiempos y valores de referencia */
function runRBenchmark(array $data): array {
    $tmpFile = '/tmp/bench.csv';
    file_put_contents($tmpFile, implode("\n", $data));

    $command = 'Rscript tests/r_performance.R ' . escapeshellarg($tmpFile);
    $output = shell_exec($command);
    @unlink($tmpFile);

    if (!$output || !preg_match('/\{.*\}/s', $output, $matches)) {
        throw new RuntimeException("Error ejecutando R o salida JSON no encontrada.");
    }

    return json_decode($matches[0], true);
}

// --- 4. MOTOR DEL BENCHMARK ---

/** Mide el rendimiento de una función callable */
function measure(string $label, callable $fn): array {
    resetMemory();
    $peakBefore = memory_get_peak_usage(true);
    
    $start = hrtime(true);
    $result = $fn();
    $end = hrtime(true);
    
    $peakAfter = memory_get_peak_usage(true);

    return [
        'label'  => $label,
        'ms'     => ($end - $start) / 1e6,
        'kb'     => max(0.0, ($peakAfter - $peakBefore) / 1024.0),
        'r_ms'   => null,
        'ratio'  => null,
        'value'  => $result // Guardamos el valor para verificar precisión
    ];
}

function buildShieldData(array $results, int $size): array {
    $statGuardLabel = "median: RobustStats ($size)";
    $mathPhpLabel = "median: MathPHP ($size)";
    $statGuardMs = null;
    $mathPhpMs = null;

    foreach ($results as $result) {
        if ($result['label'] === $statGuardLabel) {
            $statGuardMs = $result['ms'];
        }
        if ($result['label'] === $mathPhpLabel) {
            $mathPhpMs = $result['ms'];
        }
    }

    $message = 'n/a';
    if ($statGuardMs !== null && $mathPhpMs !== null && $statGuardMs > 0) {
        $ratio = $mathPhpMs / $statGuardMs;
        $message = sprintf('%.1fh faster than MathPHP', $ratio);
    }

    return [
        'schemaVersion' => 1,
        'label' => 'performance',
        'message' => $message,
        'color' => 'brightgreen'
    ];
}

// --- 5. EJECUCIÓN DEL FLUJO PRINCIPAL ---

$stats = new RobustStats();
$results = [];
$precisionWarnings = [];

foreach ($sizes as $size) {
    $data = generateDataset($size);
    
    // Obtenemos los tiempos de R para este tamaño de dataset
    try {
        $rBench = runRBenchmark($data);
    } catch (Exception $e) {
        $rBench = [];
    }

    /** SECCIÓN: MEDIANA */
    $resMedian = measure("median: RobustStats ($size)", fn() => $stats->getMedian($data));
    $resMedian['r_ms'] = $rBench['median_ms'] ?? null;
    
    $results[] = $resMedian;
    $results[] = measure("median: manual sort ($size)", fn() => manualMedian($data));
    $results[] = measure("median: MathPHP ($size)", fn() => Average::median($data));

    /** SECCIÓN: CUANTILES */
    foreach ($probs as $p) {
        $resQ = measure("quantile t7: StatGuard p=$p ($size)", fn() => QuantileEngine::calculate($data, $p, 7));
        if ($p === 0.75) $resQ['r_ms'] = $rBench['quantile_ms'] ?? null; // Comparamos p=0.75 con R
        
        $results[] = $resQ;
        $results[] = measure("quantile t7: manual p=$p ($size)", fn() => manualQuantileType7($data, $p));
        $results[] = measure("percentile: MathPHP p=$p ($size)", fn() => Descriptive::percentile($data, $p * 100));
    }

    /** SECCIÓN: MEDIAS ROBUSTAS (HUBER) */
    $resHuber = measure("mean: Huber StatGuard ($size)", fn() => $stats->getHuberMean($data));
    $resHuber['r_ms'] = $rBench['huber_ms'] ?? null;
    
    $results[] = measure("mean: arithmetic ($size)", fn() => array_sum($data) / count($data));
    $results[] = $resHuber;
    $results[] = measure("mean: MathPHP truncated 10% ($size)", fn() => Average::truncatedMean($data, 10));

    // Verificación de precisión contra R
    if (isset($rBench['huber_mu'])) {
        $diff = abs($resHuber['value'] - (float)$rBench['huber_mu']);
        if ($diff > 1e-10) {
            $precisionWarnings[] = "Huber Accuracy Warning ($size): Δ $diff";
        }
    }
}

// --- 6. RENDERIZADO DE RESULTADOS ---

if ($format === 'json') {
    $shieldData = buildShieldData($results, 100000);
    file_put_contents(
        'shield.json',
        json_encode($shieldData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    echo json_encode(['benchmarks' => $results, 'warnings' => $precisionWarnings], JSON_PRETTY_PRINT);
    exit;
}

// Formato Tabla
echo str_pad("BENCHMARK", 45) . " | " . str_pad("ms", 10) . " | " . str_pad("KB", 10) . " | " . str_pad("R (ms)", 10) . " | Ratio (PHP/R)\n";
echo str_repeat("-", 95) . "\n";

foreach ($results as $r) {
    $ratio = ($r['r_ms'] > 0) ? sprintf("%.2fx", $r['ms'] / $r['r_ms']) : "-";
    $r_time = ($r['r_ms'] !== null) ? sprintf("%.3f", $r['r_ms']) : "-";
    
    printf(
        "%-45s | %10.3f | %10.2f | %10s | %10s\n",
        $r['label'], $r['ms'], $r['kb'], $r_time, $ratio
    );
}

foreach ($precisionWarnings as $w) echo "⚠️  $w\n";