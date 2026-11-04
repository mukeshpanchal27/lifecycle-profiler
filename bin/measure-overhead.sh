#!/usr/bin/env bash
# Measures the plugin's own overhead on the wp-env development site.
#
# Loads one page many times in each case and prints the median and the 90th
# percentile of the response time, in milliseconds:
#
# - plugin inactive
# - plugin active, no session
# - plugin active, with a session
#
# Each case runs once as a logged-out visitor and once as the administrator,
# except the session case, which needs the administrator.
#
# Usage: bin/measure-overhead.sh [path] [requests]
# Run it from the repository root while wp-env is running.
set -euo pipefail

PAGE_PATH="${1:-/sample-page/}"
REQUESTS="${2:-100}"
WARMUP=10
SITE="http://localhost:8888"
PLUGIN="lifecycle-profiler"
JAR="$(mktemp)"
trap 'rm -f "$JAR"; npm exec --no -- wp-env run cli wp plugin activate "$PLUGIN" >/dev/null 2>&1 || true' EXIT

wp() {
	npm exec --no -- wp-env run cli wp "$@" 2>/dev/null
}

# Prints the median and the 90th percentile of REQUESTS page loads.
measure() {
	local label="$1"
	shift

	for _ in $(seq "$WARMUP"); do
		curl -s -o /dev/null "$@" "$SITE$PAGE_PATH"
	done

	for _ in $(seq "$REQUESTS"); do
		curl -s -o /dev/null -w '%{time_total}\n' "$@" "$SITE$PAGE_PATH"
	done | sort -n | awk -v label="$label" '
		{ t[NR] = $1 * 1000 }
		END { printf "%-40s median %7.2f ms   p90 %7.2f ms\n", label, t[int((NR + 1) / 2)], t[int(NR * 0.9)] }
	'
}

login() {
	rm -f "$JAR"
	curl -s -o /dev/null -c "$JAR" -b 'wordpress_test_cookie=WP+Cookie+check' \
		-d 'log=admin&pwd=password&testcookie=1' "$SITE/wp-login.php"
}

echo "Page: $PAGE_PATH, $REQUESTS requests per case after $WARMUP warm-up requests."

wp plugin deactivate "$PLUGIN" >/dev/null
login
measure "Inactive, logged out"
measure "Inactive, administrator" -b "$JAR"

wp plugin activate "$PLUGIN" >/dev/null
login
measure "Active, no session, logged out"
measure "Active, no session, administrator" -b "$JAR"

TOKEN="$(wp eval 'echo LifecycleProfiler\create_session_token( 1, time() + 3600 );' | tail -n 1)"
printf 'localhost\tFALSE\t/\tFALSE\t0\tlifecycle_profiler_session\t%s\n' "$TOKEN" >> "$JAR"
measure "Active, session, administrator" -b "$JAR"
