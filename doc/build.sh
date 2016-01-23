#!/bin/bash
# Build the documentation.
#
# This script does the following:
#
# - If the zf-mkdoc-theme is not currently present in the checkout, downloads
#   and extracts it.
# - Updates the mkdocs.yml to add:
#   - site_url
#   - markdown extension directives
#   - theme directory
# - Builds the documentation.
# - Restores mkdocs.yml to its original state.

# Get documentation templates and assets
if [[ ! -d zf-mkdoc-theme ]];then
    wget -O zf-mkdoc-theme.tgz https://github.com/weierophinney/zf-mkdoc-theme/archive/0.1.2.tar.gz ;
    mkdir zf-mkdoc-theme ;
    (
        cd zf-mkdoc-theme ;
        tar xzf ../zf-mkdoc-theme.tgz --strip-components=1 ;
    )
fi

# Update the mkdocs.yml
cp mkdocs.yml mkdocs.yml.orig
echo "site_url: ${SITE_URL}"
echo "markdown_extensions:" >> mkdocs.yml
echo "    - markdown.extensions.codehilite:" >> mkdocs.yml
echo "        use_pygments: False" >> mkdocs.yml
echo "    - pymdownx.superfences" >> mkdocs.yml
echo "theme_dir: zf-mkdoc-theme" >> mkdocs.yml

mkdocs build --clean
mv mkdocs.yml.orig mkdocs.yml
