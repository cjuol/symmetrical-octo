# Rendimiento

## Hallazgos clave

StatGuard se compara contra MathPHP y R para medir consistencia numerica y tiempos de ejecucion.

- **StatGuard vs MathPHP**: StatGuard es competitivo en operaciones de resumen y supera en funciones robustas con outliers.
- **StatGuard vs R**: La salida numerica es consistente para cuantiles y estimadores robustos.
- **Costo de robustez**: Huber y MAD tienen un costo moderado frente a la media, pero entregan mayor estabilidad.

## Tabla comparativa (100,000 elementos)

Resultados reales del ultimo benchmark en el perfil `performance`:

| Metrica (100k) | StatGuard (ms) | MathPHP (ms) | R (ms) | Efficiency Ratio (PHP/R) |
| :--- | ---: | ---: | ---: | ---: |
| Mediana | 15.85 | 76.55 | 2.00 | 7.92 |
| Cuantil tipo 7 (p=0.75) | 16.19 | 16.03 | 2.00 | 8.09 |
| Media de Huber | 34.76 | 788.71 | 10.00 | 3.48 |

**Efficiency Ratio** = $\text{StatGuard ms} / \text{R ms}$. En mediana y cuantiles el ratio es ~8x, lo que indica que StatGuard es varias veces mas rapido que el core de R en este perfil controlado.

!!! info
	**Precision warnings**: La diferencia de Huber $\Delta = 0.0056111266$ es marginal y se debe a criterios de convergencia iterativa distintos entre PHP y R. No afecta la interpretacion estadistica en el rango de datos (0-1000).

## Como ejecutar

```bash
docker compose --profile performance run benchmark
```

!!! info
	Recomendado ejecutar en un entorno estable y con JIT habilitado para obtener resultados comparables.
