run:
	bin/console --from="2026-05-01" --to="2026-05-02"

check-all:
	make cs
	make stat-analyze
	make unit
	make functional

cs:
	composer cs

cs-fix:
	composer cs-fix

stat-analyze:
	composer stat-analyze

unit:
	composer unit

functional:
	composer functional

build-binary:
	bash build-binary.sh
