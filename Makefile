VERSION ?= v0.1.0

## Builds the plugin
.PHONY: build
build: clean
	mkdir -p dist/mattermost
	rsync -av --progress --exclude="dist" --exclude=".git" . dist/mattermost
	cd ./dist && zip -r mod_mattermost-$(VERSION).zip mattermost

## Clean removes all build artifacts.
.PHONY: clean
clean:
	rm -fr dist/
