#!/usr/bin/env bash
#
# Integration test orchestrator for the Akeeba Panopticon Connector.
#
# Provisions a throwaway Joomla site (major 4, 5 and/or 6) inside Docker,
# builds and installs the connector package, mints a Joomla API token and
# runs the PHPUnit integration suite (phpunit-integration.xml) against it
# over real HTTP.
#
# Usage:
#   tests/integration/run-tests.sh                 # test Joomla 4, 5 and 6
#   tests/integration/run-tests.sh 5                # only Joomla 5
#   tests/integration/run-tests.sh 5 6              # Joomla 5 and 6
#   tests/integration/run-tests.sh 5 --keep         # leave the stack running
#   tests/integration/run-tests.sh 5 -- --filter=SmokeTest
#
# Arguments before a bare `--` that look like a major version (4, 5, 6, ...)
# select which Joomla majors to test (default: 4 5 6). `--keep` leaves the
# Docker stack running after the run instead of tearing it down. Anything
# after `--` (or any other unrecognised argument) is passed through to
# PHPUnit.
set -eu

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
DOCKER_DIR="${SCRIPT_DIR}/docker"
INBOX_DIR="${SCRIPT_DIR}/inbox"
WWW_DIR="${DOCKER_DIR}/www"
RELEASE_DIR="${REPO_ROOT}/release"

# shellcheck source=./lib.sh
. "${SCRIPT_DIR}/lib.sh"

# ---------------------------------------------------------------------------
# docker compose wrapper (supports both `docker compose` and `docker-compose`)
# ---------------------------------------------------------------------------
if docker compose version >/dev/null 2>&1; then
	DC() { docker compose "$@"; }
elif command -v docker-compose >/dev/null 2>&1; then
	DC() { docker-compose "$@"; }
else
	die "Docker Compose is not available. Install Docker Desktop or the compose plugin."
fi
command -v docker >/dev/null 2>&1 || die "Docker is not installed or not on PATH."

cd "${DOCKER_DIR}"

# ---------------------------------------------------------------------------
# Argument parsing
# ---------------------------------------------------------------------------
MAJORS=()
KEEP=0
PHPUNIT_ARGS=()

while [ $# -gt 0 ]; do
	case "$1" in
		--keep)
			KEEP=1
			shift
			;;
		--)
			shift
			while [ $# -gt 0 ]; do PHPUNIT_ARGS+=("$1"); shift; done
			;;
		[0-9]*)
			MAJORS+=("$1")
			shift
			;;
		*)
			PHPUNIT_ARGS+=("$1")
			shift
			;;
	esac
done

if [ "${#MAJORS[@]}" -eq 0 ]; then
	MAJORS=(4 5 6)
fi

# ---------------------------------------------------------------------------
# Environment: create .env from template, load it.
# ---------------------------------------------------------------------------
[ -f .env ] || { cp env.dist .env; log "Created .env from env.dist"; }
set -a
. ./.env
set +a

: "${DB_IMAGE:=mariadb:11.4}"
: "${DB_ROOT_PASSWORD:=rootpw}"
: "${DB_NAME:=joomla_test}"
: "${DB_USER:=root}"
: "${DB_PASSWORD:=rootpw}"
: "${DB_PREFIX:=jtest_}"
: "${WEB_PORT:=8080}"
: "${PHP_VERSION:=8.3}"

# ---------------------------------------------------------------------------
# Admin credentials, used consistently across install + token provisioning.
# ---------------------------------------------------------------------------
ADMIN_NAME="Panopticon Integration Test"
ADMIN_USER="admin"
ADMIN_PASSWORD="Integr8tion-Test-Passw0rd!"
ADMIN_EMAIL="admin@example.com"
SITE_NAME="Panopticon Connector Integration Tests"

mkdir -p "${WWW_DIR}" "${INBOX_DIR}"

# ---------------------------------------------------------------------------
# Stack bring-up / tear-down
# ---------------------------------------------------------------------------
bring_up_stack() {
	log "Building and starting the LAMP stack (PHP ${PHP_VERSION}, DB ${DB_IMAGE})"
	DC build >/dev/null
	DC up -d db web

	log "Waiting for the database to become healthy"
	local tries=0
	until [ "$(DC ps -q db | xargs -I{} docker inspect -f '{{.State.Health.Status}}' {} 2>/dev/null)" = "healthy" ]; do
		tries=$((tries + 1))
		[ "${tries}" -gt 60 ] && die "Database did not become healthy in time."
		sleep 2
	done
	ok "Database is healthy"

	log "Waiting for the web server to answer"
	local wtries=0
	until curl -s -o /dev/null "http://localhost:${WEB_PORT}/" 2>/dev/null; do
		wtries=$((wtries + 1))
		[ "${wtries}" -gt 30 ] && die "The web server did not become reachable on port ${WEB_PORT}."
		sleep 2
	done
	ok "Web server is up"
}

teardown_stack() {
	log "Bringing the Docker stack down"
	DC down -v >/dev/null 2>&1 || true
	ok "Stack down"
}

clean_www() {
	log "Cleaning web root"
	rm -rf "${WWW_DIR:?}"/* 2>/dev/null || \
		docker run --rm -v "${WWW_DIR}:/work" alpine:3 sh -c 'rm -rf /work/*' || true
}

reset_database() {
	log "Resetting database ${DB_NAME}"
	# MariaDB 11.x ships the `mariadb` client; the legacy `mysql` symlink is gone.
	DC exec -T db mariadb -uroot -p"${DB_ROOT_PASSWORD}" \
		-e "DROP DATABASE IF EXISTS \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\`;" \
		|| die "Could not reset the database."
}

# ---------------------------------------------------------------------------
# Connector build / install / token provisioning
# ---------------------------------------------------------------------------
CONNECTOR_ZIP=""

build_connector() {
	log "Building the connector package (phing git)"
	command -v phing >/dev/null 2>&1 || die "phing is not on PATH (needed to build the connector). It requires the sibling ../buildfiles checkout."
	( cd "${REPO_ROOT}" && phing git ) || die "phing git failed."

	local pkg
	pkg="$(ls -t "${RELEASE_DIR}"/pkg_panopticon-*.zip 2>/dev/null | head -1 || true)"
	[ -n "${pkg}" ] || die "No pkg_panopticon-*.zip found in ${RELEASE_DIR} after build."
	CONNECTOR_ZIP="${pkg}"
	ok "Built $(basename "${CONNECTOR_ZIP}")"
}

install_connector() {
	local zipName; zipName="$(basename "${CONNECTOR_ZIP}")"
	log "Installing ${zipName} via cli/joomla.php extension:install"
	DC exec -T web php cli/joomla.php extension:install --path="/app/release/${zipName}" \
		|| die "Connector installation failed."
	ok "Connector installed"
}

TOKEN=""

provision_token() {
	log "Provisioning a Joomla API token for ${ADMIN_USER}"
	local out
	out="$(DC exec -T web php cli/joomla.php panopticon:token:get -u "${ADMIN_USER}" --no-ansi -q)" \
		|| die "Token generation failed."

	# The command prints a couple of info lines even in quiet mode; the LAST
	# non-empty line is the token.
	TOKEN="$(printf '%s\n' "${out}" | tr -d '\r' | awk 'NF { line = $0 } END { print line }')"

	[ -n "${TOKEN}" ] || die "Could not extract a non-empty API token from panopticon:token:get output."
	ok "Token acquired"
}

# ---------------------------------------------------------------------------
# PHPUnit
# ---------------------------------------------------------------------------
run_phpunit() {
	log "Running the integration test suite (Joomla ${JOOMLA_RESOLVED})"

	if [ "${#PHPUNIT_ARGS[@]}" -gt 0 ]; then
		DC exec -T \
			-e PANOPTICON_BASE_URL="http://web/api/index.php" \
			-e PANOPTICON_API_TOKEN="${TOKEN}" \
			web phpunit -c /app/phpunit-integration.xml "${PHPUNIT_ARGS[@]}"
	else
		DC exec -T \
			-e PANOPTICON_BASE_URL="http://web/api/index.php" \
			-e PANOPTICON_API_TOKEN="${TOKEN}" \
			web phpunit -c /app/phpunit-integration.xml
	fi
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
log "Panopticon Connector integration harness — Joomla majors: ${MAJORS[*]}"

bring_up_stack

OVERALL_STATUS=0
SUMMARY_MAJOR=()
SUMMARY_VERSION=()
SUMMARY_RESULT=()

for major in "${MAJORS[@]}"; do
	log "=== Joomla ${major} ==="

	if ! acquire_joomla "${major}"; then
		warn ">>> SKIP Joomla ${major}: no stable release could be resolved"
		SUMMARY_MAJOR+=("${major}")
		SUMMARY_VERSION+=("-")
		SUMMARY_RESULT+=("SKIP")
		continue
	fi

	clean_www
	extract_joomla
	reset_database
	install_joomla
	build_connector
	install_connector
	provision_token

	MAJOR_STATUS=0
	run_phpunit || MAJOR_STATUS=1

	if [ "${MAJOR_STATUS}" -ne 0 ]; then
		OVERALL_STATUS=1
		SUMMARY_RESULT+=("FAIL")
	else
		SUMMARY_RESULT+=("PASS")
	fi

	SUMMARY_MAJOR+=("${major}")
	SUMMARY_VERSION+=("${JOOMLA_RESOLVED}")
done

if [ "${KEEP}" -eq 1 ]; then
	warn "Leaving the Docker stack up (--keep). Tear it down with: (cd ${DOCKER_DIR} && docker compose down -v)"
else
	teardown_stack
fi

echo
log "Summary"
i=0
while [ "${i}" -lt "${#SUMMARY_MAJOR[@]}" ]; do
	printf "  Joomla %-3s  version %-12s  %s\n" "${SUMMARY_MAJOR[$i]}" "${SUMMARY_VERSION[$i]}" "${SUMMARY_RESULT[$i]}"
	i=$((i + 1))
done

echo
log "Run the (host-side) unit suite with: phpunit -c phpunit.xml"

exit "${OVERALL_STATUS}"
