.PHONY: phpstan php-cs-fixer php-cs-fixer-fix rector rector-dry-run tests tests-unit tests-integration tests-functional

phpstan:
	vendor/bin/phpstan analyse

php-cs-fixer:
	vendor/bin/php-cs-fixer fix --dry-run --diff

php-cs-fixer-fix:
	vendor/bin/php-cs-fixer fix

rector:
	vendor/bin/rector process

rector-dry-run:
	vendor/bin/rector process --dry-run

tests:
	vendor/bin/phpunit

tests-unit:
	vendor/bin/phpunit --testsuite unit

tests-integration:
	vendor/bin/phpunit --testsuite integration

tests-functional:
	vendor/bin/phpunit --testsuite functional
