#!/bin/bash
set -e
docker build -t work-reporter-builder .
mkdir -p .build/bin .build/phar
CONTAINER_ID=$(docker create work-reporter-builder)
docker cp "$CONTAINER_ID":/.output/work-reporter ./.build/bin/work-reporter
docker cp "$CONTAINER_ID":/app/.build/phar/work-reporter.phar ./.build/phar/work-reporter.phar
docker rm "$CONTAINER_ID"
echo "Binary built: .build/bin/work-reporter"
