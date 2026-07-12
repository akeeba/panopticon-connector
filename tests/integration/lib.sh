#!/usr/bin/env bash
#
# Shell helpers for the Panopticon Connector integration test harness.
#
# Sourced by run-tests.sh. Ported (mostly verbatim) from
# ~/Projects/j4-akeeba/admintools/tests/integration/docker/run.sh, trimmed to
# what this repository's simpler single-`web`-container stack needs.
#
# Functions provided:
#   log/ok/warn/die              - pretty logging
#   version_lt A B                - dotted-numeric version comparison
#   resolve_joomla WANT            - resolve a (partial) Joomla version to the
#                                     latest matching stable release; prints
#                                     "<version>\t<url>" or nothing
#   find_inbox_package PREFIX      - highest cached inbox/Joomla_<prefix>*.zip
#   acquire_joomla WANT             - inbox-first, else download; sets
#                                     JOOMLA_ZIP and JOOMLA_RESOLVED
#   extract_joomla                  - unzip the acquired package into www/
#   install_joomla                  - run the Joomla CLI installer inside the
#                                     `web` container
#
# Requires the caller to have already defined: INBOX_DIR, WWW_DIR, DC (a
# function wrapping `docker compose`), ADMIN_USER, ADMIN_PASSWORD,
# ADMIN_EMAIL, SITE_NAME, DB_USER, DB_PASSWORD, DB_NAME, DB_PREFIX.

# ---------------------------------------------------------------------------
# Pretty logging
# ---------------------------------------------------------------------------
if [ -t 1 ]; then C_B='\033[0;34m'; C_G='\033[0;32m'; C_R='\033[0;31m'; C_Y='\033[0;33m'; C_0='\033[0m'; else C_B=; C_G=; C_R=; C_Y=; C_0=; fi
log()  { printf "${C_B}==>${C_0} %s\n" "$*"; }
ok()   { printf "${C_G}  ok${C_0} %s\n" "$*"; }
warn() { printf "${C_Y}  !!${C_0} %s\n" "$*"; }
die()  { printf "${C_R}ERROR:${C_0} %s\n" "$*" >&2; exit 1; }

# ---------------------------------------------------------------------------
# The list of published Joomla/WordPress versions and their download URLs,
# served (gzip-compressed) by our own Panopticon checksums service.
#   Docs: https://getpanopticon.com/checksums/index.html
# ---------------------------------------------------------------------------
SOURCES_URL="https://getpanopticon.com/checksums/sources.json.gz"

# True (0) when $1 is strictly lower than $2, comparing dotted numeric versions.
version_lt() {
	[ "$1" = "$2" ] && return 1
	[ "$(printf '%s\n%s\n' "$1" "$2" | sort -V | head -1)" = "$1" ]
}

# Resolve a (possibly partial) Joomla version to the latest matching STABLE
# release. Prints "<version>\t<download-url>" or nothing if unresolved.
resolve_joomla() {
	local want="$1"
	command -v jq     >/dev/null 2>&1 || die "jq is required to resolve the Joomla version."
	command -v gunzip >/dev/null 2>&1 || die "gunzip is required to resolve the Joomla version."

	local gz; gz="$(mktemp)"
	curl -fsSL "${SOURCES_URL}" -o "${gz}" || { rm -f "${gz}"; die "Could not download ${SOURCES_URL}"; }

	# Keep only stable Joomla releases (X.Y.Z with no -alpha/-beta/-rc suffix) whose
	# version equals the wanted value or falls under the wanted branch prefix, then
	# take the numerically highest one.
	local result
	result="$(gunzip -c "${gz}" | jq -r --arg want "${want}" '
		[ .[]
		  | select(.cms == "joomla")
		  | select(.version | test("^[0-9]+\\.[0-9]+\\.[0-9]+$"))
		  | select(.version == $want or (.version | startswith($want + "."))) ]
		| sort_by(.version | split(".") | map(tonumber))
		| if length == 0 then empty else (last | "\(.version)\t\(.url)") end
	')"
	rm -f "${gz}"
	printf '%s' "${result}"
}

# Find the highest-versioned Joomla full package already sitting in inbox/ that
# matches the requested (possibly partial) version prefix.
find_inbox_package() {
	local prefix="$1" best="" bestver="" f ver
	shopt -s nullglob
	for f in "${INBOX_DIR}"/Joomla_"${prefix}"*Full_Package.zip; do
		[ -f "${f}" ] || continue
		ver="$(basename "${f}" | sed -E 's/^Joomla_([0-9]+\.[0-9]+\.[0-9]+).*/\1/')"
		if [ -z "${bestver}" ] || [ "$(printf '%s\n%s\n' "${bestver}" "${ver}" | sort -V | tail -1)" = "${ver}" ]; then
			best="${f}"; bestver="${ver}"
		fi
	done
	shopt -u nullglob
	[ -n "${best}" ] && { echo "${best}"; return 0; }
	return 1
}

JOOMLA_ZIP=""
JOOMLA_RESOLVED=""

# Acquire a Joomla package for the given (possibly partial) version: reuse a
# cached inbox/ package if present, otherwise resolve + download. Sets
# JOOMLA_ZIP and JOOMLA_RESOLVED. Returns non-zero (without dying) if the
# requested major has no resolvable stable release, so callers can skip it.
acquire_joomla() {
	local want="$1"
	log "Acquiring Joomla ${want}"

	if JOOMLA_ZIP="$(find_inbox_package "${want}")"; then
		JOOMLA_RESOLVED="$(basename "${JOOMLA_ZIP}" | sed -E 's/^Joomla_([0-9]+\.[0-9]+\.[0-9]+).*/\1/')"
		ok "Using inbox package $(basename "${JOOMLA_ZIP}")"
		return 0
	fi

	local resolved; resolved="$(resolve_joomla "${want}")"
	[ -n "${resolved}" ] || return 1

	local url
	JOOMLA_RESOLVED="${resolved%%$'\t'*}"
	url="${resolved#*$'\t'}"
	local fname="Joomla_${JOOMLA_RESOLVED}-Stable-Full_Package.zip"
	JOOMLA_ZIP="${INBOX_DIR}/${fname}"

	log "Downloading Joomla ${JOOMLA_RESOLVED}"
	mkdir -p "${INBOX_DIR}"
	curl -fL --progress-bar -o "${JOOMLA_ZIP}" "${url}" \
		|| { rm -f "${JOOMLA_ZIP}"; die "Download failed: ${url}"; }
	ok "Downloaded ${fname} to inbox/"
}

extract_joomla() {
	log "Extracting Joomla ${JOOMLA_RESOLVED} into web root"
	command -v unzip >/dev/null 2>&1 || die "unzip is required to extract the Joomla package."
	unzip -q -o "${JOOMLA_ZIP}" -d "${WWW_DIR}"
	ok "Joomla ${JOOMLA_RESOLVED} extracted"
}

# Run the Joomla CLI installer inside the `web` container, then remove the
# installation/ directory (Joomla refuses to run while it's present).
install_joomla() {
	log "Installing Joomla ${JOOMLA_RESOLVED} via installation/joomla.php CLI"

	local -a opts=(
		installation/joomla.php install
		--site-name "${SITE_NAME}"
		--admin-user "${ADMIN_NAME}"
		--admin-username "${ADMIN_USER}"
		--admin-password "${ADMIN_PASSWORD}"
		--admin-email "${ADMIN_EMAIL}"
		--db-type mysqli
		--db-host db
		--db-user "${DB_USER}"
		--db-pass "${DB_PASSWORD}"
		--db-name "${DB_NAME}"
		--db-prefix "${DB_PREFIX}"
		--db-encryption 0
	)

	# The --public-folder option only exists from Joomla 5.0 onwards; passing it to
	# a Joomla 4.x installer is an error.
	if ! version_lt "${JOOMLA_RESOLVED}" "5.0.0"; then
		opts+=(--public-folder "")
	fi

	DC exec -T web php "${opts[@]}" || die "Joomla CLI installation failed."

	rm -rf "${WWW_DIR}/installation"
	ok "Joomla ${JOOMLA_RESOLVED} installed"
}
