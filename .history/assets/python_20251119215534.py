import os
from urllib.parse import urlsplit
from urllib.request import urlopen

# List of font URLs
FONT_URLS = [
    "https://fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2JL7SUc.woff2",
    "https://fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa0ZL7SUc.woff2",
    "https://fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2ZL7SUc.woff2",
    "https://fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1pL7SUc.woff2",
    "https://fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2pL7SUc.woff2",
    "https://fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa25L7SUc.woff2",
    "https://fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1ZL7.woff2",
]

# Folder to save downloaded fonts
OUTPUT_DIR = "fonts_inter"

os.makedirs(OUTPUT_DIR, exist_ok=True)

def download_file(url: str, output_dir: str) -> None:
    """Download a single file from url into output_dir."""
    # Extract filename from URL
    path = urlsplit(url).path
    filename = os.path.basename(path)

    # Full path where the file will be saved
    filepath = os.path.join(output_dir, filename)

    print(f"Downloading {url} -> {filepath}")

    try:
        with urlopen(url) as response, open(filepath, "wb") as out_file:
            data = response.read()
            out_file.write(data)
        print(f"✓ Saved: {filepath}")
    except Exception as e:
        print(f"✗ Failed to download {url}: {e}")

def main():
    for url in FONT_URLS:
        download_file(url, OUTPUT_DIR)

if __name__ == "__main__":
    main()
