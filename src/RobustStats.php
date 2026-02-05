<?php

declare(strict_types=1);

namespace Cjuol\SymmetricalOcto;

use Cjuol\SymmetricalOcto\Contracts\StatsInterface;
use Cjuol\SymmetricalOcto\Traits\DataProcessorTrait;

/**
 * RobustStats - Biblioteca de Estadística Robusta
 * * Implementa cálculos resistentes a valores atípicos (outliers) 
 * utilizando S*, MAD y el Rango Intercuartílico (IQR).
 */
class RobustStats implements StatsInterface
{
    use DataProcessorTrait;

    // ========== FUNCIONES PÚBLICAS - INTERFAZ Y CONTRATOS ==========

    /**
     * Calcula la media aritmética (vulnerable a outliers, mantenida para comparativa).
     */
    public function getMedia(array $datos): float
    {
        return $this->calcularMedia($this->prepararDatos($datos, false));
    }

    /**
     * Calcula la mediana (medida de tendencia central robusta).
     */
    public function getMediana(array $datos): float
    {
        return $this->calcularMediana($this->prepararDatos($datos, true));
    }

    /**
     * Implementación del contrato: Devuelve la desviación robusta.
     */
    public function getDesviacion(array $datos): float
    {
        return $this->getDesviacionRobusta($datos);
    }

    /**
     * Calcula la desviación robusta (S*) resistente a outliers.
     */
    public function getDesviacionRobusta(array $datos): float
    {
        return $this->calcularDesviacionRobusta($this->prepararDatos($datos, true));
    }

    /**
     * Implementación del contrato: Devuelve el CV robusto.
     */
    public function getCV(array $datos): float
    {
        return $this->getCVr($datos);
    }

    /**
     * Calcula el Coeficiente de Variación Robusto (CVr%).
     */
    public function getCVr(array $datos): float
    {
        return $this->calcularCVr($this->prepararDatos($datos, true));
    }

    /**
     * Calcula la varianza robusta (cuadrado de S*).
     */
    public function getVarianzaRobusta(array $datos): float
    {
        return pow($this->getDesviacionRobusta($datos), 2);
    }

    /**
     * Calcula el Rango Intercuartílico (IQR).
     */
    public function getIQR(array $datos): float
    {
        return $this->calcularIQR($this->prepararDatos($datos, true));
    }

    /**
     * Calcula la Desviación Absoluta de la Mediana (MAD).
     */
    public function getMAD(array $datos): float
    {
        return $this->calcularMAD($this->prepararDatos($datos, true));
    }

    /**
     * Detecta valores atípicos (outliers) usando el método de Tukey.
     */
    public function getOutliers(array $datos): array
    {
        return $this->detectarOutliers($this->prepararDatos($datos, true));
    }

    /**
     * Calcula los intervalos de confianza al 95% basados en la mediana.
     */
    public function getIntervalosConfianza(array $datos): array
    {
        return $this->calcularIntervalosConfianza($this->prepararDatos($datos, true));
    }

    /**
     * Obtiene un resumen completo de todas las métricas estadísticas robustas.
     */
    public function obtenerResumen(array $datos, bool $ordenar = true, int $decimales = 2): array
    {
        $d = $this->prepararDatos($datos, $ordenar);
        
        return [
            'media'               => round($this->calcularMedia($d), $decimales),
            'mediana'             => round($this->calcularMediana($d), $decimales),
            'desviacionRobusta'   => round($this->calcularDesviacionRobusta($d), $decimales),
            'varianzaRobusta'     => round(pow($this->calcularDesviacionRobusta($d), 2), $decimales),
            'CVr'                 => round($this->calcularCVr($d), $decimales),
            'IQR'                 => round($this->calcularIQR($d), $decimales),
            'MAD'                 => round($this->calcularMAD($d), $decimales),
            'outliers'            => $this->detectarOutliers($d),
            'intervalosConfianza' => $this->calcularIntervalosConfianza($d),
            'count'               => count($d)
        ];
    }

    // ========== FUNCIONES PRIVADAS - MOTOR DE CÁLCULO PURO ==========

    private function calcularMedia(array $datos): float
    {
        return array_sum($datos) / count($datos);
    }

    private function calcularMediana(array $datos): float
    {
        $n = count($datos);
        $m = intdiv($n, 2);

        if ($n % 2 === 0) {
            return ($datos[$m - 1] + $datos[$m]) / 2.0;
        }
        return (float) $datos[$m];
    }

    /**
     * Fórmula: S* = (1.25 / 1.35) × (IQR / √n)
     */
    private function calcularDesviacionRobusta(array $datos): float
    {
        $n = count($datos);
        $iqr = $this->calcularIQR($datos);
        return (1.25 / 1.35) * ($iqr / sqrt($n));
    }

    private function calcularCVr(array $datos): float
    {
        $mediana = $this->calcularMediana($datos);
        if (abs($mediana) < 1e-9) return 0.0;

        return ($this->calcularDesviacionRobusta($datos) / abs($mediana)) * 100;
    }

    private function calcularIQR(array $datos): float
    {
        $q1 = $this->calcularPercentil($datos, 25);
        $q3 = $this->calcularPercentil($datos, 75);
        return $q3 - $q1;
    }

    private function calcularMAD(array $datos): float
    {
        $mediana = $this->calcularMediana($datos);
        $desviaciones = array_map(fn($x) => abs($x - $mediana), $datos);
        sort($desviaciones);
        return $this->calcularMediana($desviaciones);
    }

    private function detectarOutliers(array $datos): array
    {
        $iqr = $this->calcularIQR($datos);
        $q1 = $this->calcularPercentil($datos, 25);
        $q3 = $this->calcularPercentil($datos, 75);

        $limiteInf = $q1 - 1.5 * $iqr;
        $limiteSup = $q3 + 1.5 * $iqr;

        return array_values(array_filter($datos, fn($x) => $x < $limiteInf || $x > $limiteSup));
    }

    private function calcularIntervalosConfianza(array $datos): array
    {
        $mediana = $this->calcularMediana($datos);
        $margen = 1.96 * $this->calcularDesviacionRobusta($datos);
        
        return [
            'superior' => $mediana + $margen,
            'inferior' => $mediana - $margen,
        ];
    }

    private function calcularPercentil(array $datosOrdenados, int $percentil): float
    {
        $index = ($percentil / 100) * (count($datosOrdenados) - 1);
        $low = (int)floor($index);
        $high = (int)ceil($index);
        $fraction = $index - $low;

        return $datosOrdenados[$low] + $fraction * ($datosOrdenados[$high] - $datosOrdenados[$low]);
    }
}