#! /bin/bash
set -e

die () {
    echo >&2 "$@"
    exit 1
}
[ "$#" -eq 2 ] || die "Usage: $0 /path/to/original.tar.gz /path/to/modified.tar.gz"
[ -f "$1" ] || die "File $1 does not exist"
[ -f "$2" ] || die "File $2 does not exist"

old=$(mktemp -d -t diff-files-old-XXXXXXXX)
tar xf "$1" -C "$old"

new=$(mktemp -d -t diff-files-new-XXXXXXXX)
tar xf "$2" -C "$new"

dif=$(mktemp -d -t diff-files-XXXXXXXX)
out="$(pwd)/$(basename -- "$dif").tar.gz"

rsync -rvcmq --compare-dest="$old/" "$new/" "$dif"
cd "$dif" && tar zcf "$out" * && cd - > /dev/null
echo "Diff archive output at $out"