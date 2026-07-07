# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

- `PermissionMap::fromToolClasses()` throws `InvalidPermissionMapException`
  when attributes map one tool name to two different permissions (was a
  silent last-one-wins overwrite); explicit overrides still win by design.
- `/build` added to `.gitattributes` `export-ignore` — the empty coverage
  directory no longer ships in the dist archive.
