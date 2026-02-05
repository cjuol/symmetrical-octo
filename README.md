# 🐙 Symmetrical Octo: Robust Stats Suite for PHP
[![Latest Version on Packagist](https://img.shields.io/packagist/v/cjuol/symmetrical-octo.svg?style=flat-square)](https://packagist.org/packages/cjuol/symmetrical-octo)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![PHP Tests](https://github.com/cjuol/symmetrical-octo/actions/workflows/php-tests.yml/badge.svg)](https://github.com/cjuol/symmetrical-octo/actions)

Symmetrical Octo es una suite avanzada de analisis estadistico. Su proposito es permitir a los desarrolladores enfrentar la Estadistica Clasica contra la Estadistica Robusta para identificar sesgos, ruido y errores de medicion de forma automatizada.

## 💡 Motivacion y Origen

En entornos como el seguimiento deportivo o la telemetria, los datos suelen contener "ruido" (fallos de sensores, dias excepcionales). La estadistica clasica (Media) es un "cristal" que se rompe ante un solo valor extremo.

Symmetrical Octo actua como un filtro de calidad, permitiendote saber cuando puedes confiar en la media y cuando debes recurrir a la robustez de la mediana y el MAD.

## 🚀 Nuevas Funcionalidades (v1.1.0)

Esta version transforma la biblioteca en una herramienta integral con arquitectura SOLID:

- **ClassicStats**: Implementacion completa de estadistica descriptiva tradicional.
- **StatsComparator**: El "cerebro" que analiza la fidelidad de tus datos y emite veredictos.
- **ExportableTrait**: Exportacion nativa a CSV y JSON integrada en todas las clases.
- **Arquitectura de Traits e Interfaces**: Validacion automatica de datos y extensibilidad garantizada.

## 🛠 Instalacion

```bash
composer require cjuol/symmetrical-octo
```

## 📖 Guia de Uso

### 1. El Comparador (Deteccion de Sesgos)

Es la herramienta mas potente de la suite. Analiza si la media clasica esta "muriendo" por culpa de los outliers.

```php
use Cjuol\SymmetricalOcto\StatsComparator;

$comparator = new StatsComparator();
$datos = [10, 12, 11, 15, 10, 1000]; // El 1000 es ruido

$analisis = $comparator->analizar($datos);

echo $analisis['veredicto'];
// ALERTA: Datos altamente influenciados por outliers. Se recomienda usar metricas Robustas.
```

### 2. Exportacion Instantanea

Cualquier clase estadistica puede generar informes listos para descargar o enviar por API:

```php
$robust = new \Cjuol\SymmetricalOcto\RobustStats();

// Generar un CSV para abrir en Excel
file_put_contents('informe.csv', $robust->toCsv($datos));

// O un JSON para tu Frontend
echo $robust->toJson($datos);
```

## 📊 Comparativa de Metricas

| Metrica | ClassicStats | RobustStats | Impacto de Outliers |
| :--- | :--- | :--- | :--- |
| Centro | Media | Mediana | Alta en Clasica |
| Dispersion | Desv. Estandar | MAD (Escalado) | Extremo en Clasica |
| Variabilidad | CV% | CVr% | Muy alto en Clasica |
| Exportable | ✅ Si | ✅ Si | - |

## 🧪 Fundamento Matematico

### Desviacion Robusta Escalada

Para que el comparador sea justo, escalamos el MAD para hacerlo comparable a la desviacion estandar en distribuciones normales:

$$\sigma_{robust} = MAD \times 1.4826$$

### Coeficiente de Variacion Robusto ($CV_r$)

Calculado sobre la mediana para evitar que un solo valor extremo infle la percepcion de volatilidad:

$$CV_r = \left( \frac{\sigma_{robust}}{|\tilde{x}|} \right) \times 100$$

## 🚦 Tests y Calidad

Validacion completa mediante PHPUnit asegurando una cobertura total en calculos y validaciones de datos.

```bash
./vendor/bin/phpunit tests
```

## 📄 Licencia

Este proyecto esta bajo la Licencia MIT. Consulta el archivo LICENSE para mas detalles.

Desarrollado con ❤️ por cjuol.
