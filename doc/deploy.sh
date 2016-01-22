#!/bin/bash
# Deploy documentation to gh-pages
#
# Environment variables that may be of use:
#
# - SITE_URL indicates the URL to the site, to ensure search works;
# - GH_USER_NAME indicates the GitHub author name to use;
# - GH_USER_EMAIL indicates the email address for that author;
# - GH_REF indicates the URI, without scheme or user-info, to the repository;
# - GH_TOKEN is the personal security token to use for commits.
#
# All of the above are exported via the project .travis.yml file (with
# GH_TOKEN being encrypted and present in the `secure` key). The user details
# need to match the token used for this to work.

set -o errexit -o nounset

# Get curent commit revision
rev=$(git rev-parse --short HEAD)

# Get documentation templates and assets
wget -O zf-mkdoc-theme.tgz https://github.com/weierophinney/zf-mkdoc-theme/archive/0.1.1.tar.gz
mkdir zf-mkdoc-theme
(
    cd zf-mkdoc-theme
    tar xzf ../zf-mkdoc-theme.tgz --strip-components=1
)

# Update the mkdocs.yml
echo "site_url: ${SITE_URL}"
echo "markdown_extensions:" >> mkdocs.yml
echo "    - markdown.extensions.codehilite:" >> mkdocs.yml
echo "        use_pygments: False" >> mkdocs.yml
echo "    - pymdownx.superfences" >> mkdocs.yml
echo "theme_dir: zf-mkdoc-theme" >> mkdocs.yml

# Initialize gh-pages checkout
mkdir -p doc/html
(
    cd doc/html
    git init
    git config user.name "${GH_USER_NAME}"
    git config user.email "${GH_USER_EMAIL}"
    git remote add upstream "https://${GH_TOKEN}@${GH_REF}"
    git fetch upstream
    git reset upstream/gh-pages
)

# Build the documentation
mkdocs build --clean

# Commit and push the documentation to gh-pages
(
    cd doc/html
    touch .
    git add -A .
    git commit -m "Rebuild pages at ${rev}"
    git push upstream HEAD:gh-pages
)
