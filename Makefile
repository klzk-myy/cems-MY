# Makefile
# Convenience targets for local CI/CD.

.PHONY: help lint security test ci deploy deploy-staging deploy-production

help:
	@echo "Available targets:"
	@echo "  make lint              - Run lint/code quality checks"
	@echo "  make security          - Run security audit"
	@echo "  make test              - Run unit and feature tests"
	@echo "  make ci                - Run lint + security + test (no deploy)"
	@echo "  make deploy ENV=staging or ENV=production - Run full pipeline including deploy"

lint:
	@scripts/ci/lint.sh

security:
	@scripts/ci/security.sh

test:
	@scripts/ci/test.sh

ci: lint security test

deploy:
	@if [ -z "$(ENV)" ]; then \
		echo "ERROR: ENV is required. Example: make deploy ENV=staging"; \
		exit 1; \
	fi
	@if [ "$(ENV)" != "staging" ] && [ "$(ENV)" != "production" ]; then \
		echo "ERROR: ENV must be 'staging' or 'production'. Got: $(ENV)"; \
		exit 1; \
	fi
	@scripts/ci/pipeline.sh "$(ENV)"

deploy-staging:
	@scripts/ci/pipeline.sh staging

deploy-production:
	@scripts/ci/pipeline.sh production
