#!/usr/bin/env Rscript

args <- commandArgs(trailingOnly = TRUE)
if (length(args) < 1) {
  cat('{"error":"missing_csv_path"}\n')
  quit(status = 1)
}

csv_path <- args[1]

suppressMessages(library(MASS))
suppressMessages(library(jsonlite))

# Load data once; timing should only include computation
values <- scan(csv_path, sep = ",", quiet = TRUE)

median_time <- system.time({
  med <- median(values)
})["elapsed"] * 1000

quantile_time <- system.time({
  q <- quantile(values, probs = 0.75, type = 7, names = FALSE)
})["elapsed"] * 1000

huber_time <- system.time({
  h <- MASS::huber(values)
  mu <- h$mu
})["elapsed"] * 1000

output <- list(
  median_ms = as.numeric(median_time),
  quantile_ms = as.numeric(quantile_time),
  huber_ms = as.numeric(huber_time),
  median = as.numeric(med),
  quantile = as.numeric(q),
  huber_mu = as.numeric(mu)
)

cat(jsonlite::toJSON(output, auto_unbox = TRUE))
cat("\n")
