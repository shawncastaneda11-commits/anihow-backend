#!/usr/bin/env bash
# Renders every figure to PNG (200 dpi, for Word) and SVG. Run from docs/manuscript/figures.
# Needs Graphviz (dot, neato) and the Liberation Sans font.
set -euo pipefail
python3 src/gen_fig7.py
python3 src/gen_dfd.py
for src in src/fig*.dot; do
  case "$src" in *_dfd_level*) continue ;; esac
  name=$(basename "$src" .dot)
  engine=dot
  grep -q 'layout=neato' "$src" && engine=neato
  "$engine" -Tpng -Gdpi=200 "$src" -o "$name.png"
  "$engine" -Tsvg "$src" -o "$name.svg"
done
