#!/usr/bin/env bash
# Verifies that all version numbers agree. Pass a git tag (v1.2.3) to also
# compare against it.
set -euo pipefail

cd "$(dirname "$0")/.."

header=$(grep -E '^ \* Version:' rmd-opening-hours.php | awk '{print $3}')
constant=$(grep -E "define\( 'RMD_OH_VERSION'" rmd-opening-hours.php | sed -E "s/.*'([0-9.]+)'.*/\1/")
package=$(node -p "require('./package.json').version")
stable=$(grep -E '^Stable tag:' readme.txt | awk '{print $3}')

echo "Plugin header:   $header"
echo "RMD_OH_VERSION:  $constant"
echo "package.json:    $package"
echo "readme.txt:      $stable"

status=0
for value in "$constant" "$package" "$stable"; do
	if [ "$value" != "$header" ]; then
		status=1
	fi
done

if [ "${1:-}" != "" ]; then
	tag="${1#v}"
	echo "Tag:             $tag"
	if [ "$tag" != "$header" ]; then
		status=1
	fi
fi

for block in src/blocks/*/block.json; do
	block_version=$(node -p "require('./$block').version")
	if [ "$block_version" != "$header" ]; then
		echo "$block: $block_version"
		status=1
	fi
done

if [ $status -ne 0 ]; then
	echo "Version mismatch." >&2
	exit 1
fi
echo "All versions match."
