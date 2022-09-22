#! /bin/bash
set -e
old=$(basename -- "$1")
old="/tmp/${old%%.*}/"
rm -rf "$old"
mkdir "$old" && tar xf "$1" -C "$old"

new=$(basename -- "$2")
new="/tmp/${new%%.*}/"
rm -rf "$new"
mkdir "$new" && tar xf "$2" -C "$new"

tmp=$(mktemp -d -t diff-files-XXXXXXXX)
dif=$(basename -- "$tmp")
cur=$(pwd)

rsync -rvcm --compare-dest="$old" "$new" "$tmp"
cd "$tmp" && tar zcf "$cur/$dif.tar.gz" * && cd -
