"""Build the ORWO Core plugin into dist/orwo-core.zip.

Works from either layout: the workspace, where the plugin sits in wordpress-plugin/orwo-core/, and the released
repository, where it sits in plugin/orwo-core/ beside the theme.
"""
import re
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

theme_root = Path(__file__).resolve().parents[1]

candidates = [
    theme_root / "plugin" / "orwo-core",                              # repository layout
    theme_root.parents[1] / "wordpress-plugin" / "orwo-core",         # workspace layout
]
plugin_root = next((path for path in candidates if (path / "orwo-core.php").exists()), None)
if plugin_root is None:
    raise SystemExit("Could not find orwo-core. Looked in: " + ", ".join(str(path) for path in candidates))

output = theme_root / "dist" / "orwo-core.zip"
output.parent.mkdir(exist_ok=True)

files = [plugin_root / "orwo-core.php", plugin_root / "readme.txt"]
for name in ("includes", "assets"):
    directory = plugin_root / name
    if directory.exists():
        files.extend(path for path in directory.rglob("*") if path.is_file())
files = [f for f in files if f.exists()]

for file in files:
    relative = file.relative_to(plugin_root)
    if any(part.startswith(".") for part in relative.parts) or file.suffix.lower() in (".key", ".pem", ".pfx", ".p12", ".env"):
        raise ValueError("Unexpected sensitive file in plugin distribution: " + str(relative))
    if file.suffix.lower() in (".php", ".json", ".css", ".js", ".txt") and re.search(
        r"(github_pat_[A-Za-z0-9_]{30,}|gh[pousr]_[A-Za-z0-9]{30,})", file.read_text(encoding="utf-8", errors="ignore")
    ):
        raise ValueError("Token-like text found in " + str(relative))

with ZipFile(output, "w", ZIP_DEFLATED) as archive:
    for file in sorted(set(files)):
        archive.write(file, Path("orwo-core") / file.relative_to(plugin_root))

print("Built:", output, output.stat().st_size // 1024, "KB", "from", plugin_root)
