#!/bin/zsh
cd $(dirname $0)/..
ENABLE_MASTODON_APPS_VERSION=$(egrep define...ENABLE_MASTODON_APPS_VERSION enable-mastodon-apps.php | egrep -o "[0-9]+\.[0-9]+\.[0-9]+")

echo Enable Mastodon Apps Release $ENABLE_MASTODON_APPS_VERSION
echo "=================================="
echo

svn info | grep ^URL: | grep -q plugins.svn.wordpress.org/enable-mastodon-apps/trunk
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Not on the SVN path https://plugins.svn.wordpress.org/enable-mastodon-apps/trunk"
	return
fi
echo -ne "\033[32m✔\033[0m "
echo "On the SVN path https://plugins.svn.wordpress.org/enable-mastodon-apps/trunk"

git symbolic-ref --short HEAD | grep -q ^main$
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Not on git branch main"
	return
fi
echo -ne "\033[32m✔\033[0m "
echo "On git branch main"

svn update > /dev/null
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Failed to update svn. Try yourself:"
	echo
	echo ❯ svn update
	return
fi
echo -ne "\033[32m✔\033[0m "
echo "svn up to date"

git diff-files --quiet
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Unstaged changes in git"
	echo
	echo ❯ git status
	git status
	return
fi
echo -ne "\033[32m✔\033[0m "
echo "No unstaged changes in git"

git tag | egrep -q ^$ENABLE_MASTODON_APPS_VERSION\$
if [ $? -eq 0 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Tag $ENABLE_MASTODON_APPS_VERSION already exists"
	return
fi
echo -ne "\033[32m✔\033[0m "
echo "Tag $ENABLE_MASTODON_APPS_VERSION doesn't exist yet"

grep -q "Stable tag: $ENABLE_MASTODON_APPS_VERSION" README.md
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Stable tag not updated in README.md:"
	awk '/Stable tag: / { print "  " $0 }' README.md
	return
fi
echo -ne "\033[32m✔\033[0m "
echo "Stable tag updated in README.md:"
awk '/Stable tag: / { print "  " $0 }' README.md

grep -q "Version: $ENABLE_MASTODON_APPS_VERSION" enable-mastodon-apps.php
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Version not updated in enable-mastodon-apps.php:"
	awk '/Version: / { print "  " $0 }' enable-mastodon-apps.php
	return
fi
echo -ne "\033[32m✔\033[0m "
echo "Version updated in enable-mastodon-apps.php:"
awk '/Version: / { print "  " $0 }' enable-mastodon-apps.php

grep -q "### $ENABLE_MASTODON_APPS_VERSION" CHANGELOG.md
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Changelog not found in CHANGELOG.md"
	return
fi
echo -ne "\033[32m✔\033[0m "
echo "Changelog updated in CHANGELOG.md:"
awk '/^### '$ENABLE_MASTODON_APPS_VERSION'/ { print "  " $0; show = 1; next } /^###/ { show = 0 } { if ( show ) print "  " $0 }' CHANGELOG.md
grep -q "### $ENABLE_MASTODON_APPS_VERSION" README.md
if [ $? -eq 1 ]; then
	echo -n "Changelog not found in README.md"
	echo -e "\033[31m✘\033[0m"
	return
fi
echo -ne "\033[32m✔\033[0m "
echo "Changelog updated in README.md:"
awk '/^### '$ENABLE_MASTODON_APPS_VERSION'/ { print "  " $0; show = 1; next } /^###/ { show = 0 } { if ( show ) print "  " $0 }' README.md
svn status | egrep -q "^[?]"
if [ $? -eq 0 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Unknown files in svn"
	echo
	echo ❯ svn status
	svn status
	return
fi
echo -ne "\033[32m✔\033[0m "
echo "No unknown files in svn"
echo

echo -ne "\033[32m✔\033[0m "
echo "All looks good, ready to tag and commit!"
echo -n ❯ git push
read
git push
echo -n ❯ git tag $ENABLE_MASTODON_APPS_VERSION
read
git tag $ENABLE_MASTODON_APPS_VERSION
echo -n ❯ git push origin $ENABLE_MASTODON_APPS_VERSION
read
git push origin $ENABLE_MASTODON_APPS_VERSION
echo -n '❯ svn ci -m "enable-mastodon-apps '$ENABLE_MASTODON_APPS_VERSION'" && svn cp https://plugins.svn.wordpress.org/enable-mastodon-apps/trunk https://plugins.svn.wordpress.org/enable-mastodon-apps/tags/'$ENABLE_MASTODON_APPS_VERSION' -m "Release '$ENABLE_MASTODON_APPS_VERSION'"'
read
svn ci -m "enable-mastodon-apps $ENABLE_MASTODON_APPS_VERSION" && svn cp https://plugins.svn.wordpress.org/enable-mastodon-apps/trunk https://plugins.svn.wordpress.org/enable-mastodon-apps/tags/$ENABLE_MASTODON_APPS_VERSION -m "Release $ENABLE_MASTODON_APPS_VERSION"
echo Now create a new release on GitHub: https://github.com/akirk/enable-mastodon-apps/releases/new\?tag=$ENABLE_MASTODON_APPS_VERSION
