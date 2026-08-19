#!/usr/bin/env bash

#
# Extension test runner based on docker, using the same "typo3/core-testing-*"
# images the TYPO3 core CI runs on. Trimmed down from TYPO3 core's
# Build/Scripts/runTests.sh to the suites this extension actually has.
#

trap 'echo "runTests.sh SIGINT signal emitted"; exit 2' SIGINT

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHP_VERSION="8.5"
TEST_SUITE="unit"
XDEBUG_MODE="off"

# Image tags per TYPO3 core's Build/Scripts/runTests.sh (14.x branch); core-testing
# images moved from Docker Hub to ghcr.io and are tagged by build number, not PHP version.
getPhpImageVersion() {
    case ${1} in
        8.2) echo -n "1.15" ;;
        8.3) echo -n "1.16" ;;
        8.4) echo -n "1.8" ;;
        8.5) echo -n "1.8" ;;
    esac
}

loadHelp() {
    read -r -d '' HELP <<EOF
bunny_cdn extension test runner, container based (docker).

Usage: $0 [options] [-- phpunit options]

Options:
    -s <...>
        Test suite to run
            - unit (default): PHP unit tests (Build/UnitTests.xml)
            - composerInstall: "composer install"
            - composerInstallMax: "composer update", highest dependencies
            - composerInstallMin: "composer update --prefer-lowest"
            - cgl: test and fix all php files for coding guideline compliance
            - lintPhp: PHP syntax lint (php -l)
            - lintYaml: YAML lint (Configuration/Services.yaml with tag parsing, rest without)
            - clean: remove .phpunit.cache and other test artifacts

    -p <8.2|8.3|8.4|8.5>
        PHP minor version to use (default: 8.5)

    -x
        Send xdebug information to a local IDE listening on port 9003

    -n
        Only with -s cgl
        Dry-run: report violations without fixing them

    -h
        Show this help

Examples:
    ./Build/Scripts/runTests.sh
    ./Build/Scripts/runTests.sh -s unit -- --filter testSomething
    ./Build/Scripts/runTests.sh -p 8.3 -s composerInstall
    ./Build/Scripts/runTests.sh -s cgl -n
EOF
    echo "${HELP}"
}

CGL_DRY_RUN=()
while getopts "s:p:xnh" OPT; do
    case ${OPT} in
        s) TEST_SUITE="${OPTARG}" ;;
        p) PHP_VERSION="${OPTARG}" ;;
        x) XDEBUG_MODE="debug" ;;
        n) CGL_DRY_RUN=(--dry-run --diff) ;;
        h) loadHelp; exit 0 ;;
        *) loadHelp; exit 1 ;;
    esac
done
shift $((OPTIND - 1))
if [ "${1:-}" = "--" ]; then shift; fi

if ! [[ ${PHP_VERSION} =~ ^(8.2|8.3|8.4|8.5)$ ]]; then
    echo "Invalid option -p ${PHP_VERSION}" >&2
    exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
    echo "This script relies on docker. Please install docker." >&2
    exit 1
fi

IMAGE_PHP="ghcr.io/typo3/core-testing-php$(echo "${PHP_VERSION}" | tr -d '.'):$(getPhpImageVersion "${PHP_VERSION}")"
CONTAINER_COMMON_PARAMS=(
    --rm
    -u "$(id -u):$(id -g)"
    -v "${ROOT_DIR}:/app"
    -w /app
    -e "TYPO3_CONTEXT=Testing"
    -e "XDEBUG_MODE=${XDEBUG_MODE}"
    -e "XDEBUG_CONFIG=client_host=host.docker.internal"
    --add-host "host.docker.internal:host-gateway"
)

SUITE_EXIT_CODE=0

case ${TEST_SUITE} in
    unit)
        docker run "${CONTAINER_COMMON_PARAMS[@]}" "${IMAGE_PHP}" \
            php vendor/bin/phpunit -c Build/UnitTests.xml "$@"
        SUITE_EXIT_CODE=$?
        ;;
    composerInstall)
        docker run "${CONTAINER_COMMON_PARAMS[@]}" "${IMAGE_PHP}" \
            composer install --no-progress "$@"
        SUITE_EXIT_CODE=$?
        ;;
    composerInstallMax)
        docker run "${CONTAINER_COMMON_PARAMS[@]}" "${IMAGE_PHP}" \
            composer update --no-progress "$@"
        SUITE_EXIT_CODE=$?
        ;;
    composerInstallMin)
        docker run "${CONTAINER_COMMON_PARAMS[@]}" "${IMAGE_PHP}" \
            composer update --no-progress --prefer-lowest "$@"
        SUITE_EXIT_CODE=$?
        ;;
    cgl)
        docker run "${CONTAINER_COMMON_PARAMS[@]}" "${IMAGE_PHP}" \
            php vendor/bin/php-cs-fixer fix "${CGL_DRY_RUN[@]}" "$@"
        SUITE_EXIT_CODE=$?
        ;;
    lintPhp)
        COMMAND="find Classes/ Configuration/ Tests/ ext_localconf.php ext_emconf.php ext_conf_template.txt -name '*.php' -print0 | xargs -0 -n1 -P \$(nproc 2>/dev/null || echo 4) php -dxdebug.mode=off -l >/dev/null"
        docker run "${CONTAINER_COMMON_PARAMS[@]}" "${IMAGE_PHP}" /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    lintYaml)
        COMMAND="php -dxdebug.mode=off vendor/bin/yaml-lint --parse-tags Configuration/Services.yaml && find Configuration/ \\( -name '*.yaml' -o -name '*.yml' \\) ! -name 'Services.yaml' | xargs -r php -dxdebug.mode=off vendor/bin/yaml-lint --no-parse-tags"
        docker run "${CONTAINER_COMMON_PARAMS[@]}" "${IMAGE_PHP}" /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    clean)
        echo -n "Clean test artifacts ... "
        rm -rf "${ROOT_DIR}/.phpunit.cache" "${ROOT_DIR}/typo3temp/var/tests"
        echo "done"
        ;;
    *)
        echo "Invalid option -s ${TEST_SUITE}" >&2
        loadHelp
        exit 1
        ;;
esac

exit ${SUITE_EXIT_CODE}
