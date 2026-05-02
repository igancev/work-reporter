ARG COMPOSER_VERSION="2.8"
FROM ghcr.io/igancev/work-reporter-builder/bin-builder:0.0.2 AS builder

WORKDIR /app
COPY . .
RUN rm -f composer.lock && rm -rf vendor
RUN composer install --no-dev --ignore-platform-reqs

RUN mkdir -p .build/phar .build/bin
RUN /usr/local/bin/box compile

RUN mkdir -p ./buildroot/bin
RUN cp /build-tools/build/bin/micro.sfx ./buildroot/bin

RUN /build-tools/static-php-cli/bin/spc micro:combine .build/phar/work-reporter.phar --output=.build/bin/work-reporter
RUN chmod +x .build/bin/work-reporter

RUN mkdir -p /.output
RUN cp .build/bin/work-reporter /.output/work-reporter
ENTRYPOINT ["/.output/work-reporter"]
