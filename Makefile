up:
	./vendor/bin/sail up -d
down:
	./vendor/bin/sail stop
migrate:
	./vendor/bin/sail artisan migrate

npm-install:
	./vendor/bin/sail npm install --legacy-peer-deps

.PHONY: build-dev

build-dev:
	./vendor/bin/sail npm install --legacy-peer-deps
	./vendor/bin/sail npm run dev &

build-prod:
	./vendor/bin/sail npm install --legacy-peer-deps
	./vendor/bin/sail npm run build &

# создание коанд name=your-command-name
command-create:
	./vendor/bin/sail php artisan make:command $(name)
# запуск тестовой команды для тестирования функционала.
command-test:
	./vendor/bin/sail artisan app:test-command

test:
	./vendor/bin/sail test

test-name:
	./vendor/bin/sail test --filter $(name)

update-models:
	./vendor/bin/sail artisan ide-helper:models

db-refresh:
	./vendor/bin/sail artisan migrate:fresh --seed




