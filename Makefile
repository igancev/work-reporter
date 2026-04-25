run:
	bin/console work:report --from 2026-02-20 --to 2026-02-20

check-all:
	make cs
	make stat-analyze
	make unit

cs:
	composer cs

cs-fix:
	composer cs-fix

stat-analyze:
	composer stat-analyze

unit:
	composer unit

build-binary:
	bash build-binary.sh
