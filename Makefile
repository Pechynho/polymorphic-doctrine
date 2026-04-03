.PHONY: phpstan php-cs-fixer php-cs-fixer-fix rector rector-dry-run

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
