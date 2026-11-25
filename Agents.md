# Agent Guidelines

- Before creating or editing documentation, read the [documentation guidelines](docs/Agents.md) and follow them for all files in `docs/`.
- Before implementing code or modifying application structure, read the [implementation guidelines](src/Agents.md) and follow them for all files in `src/`.
- Agent Skills are stored in the `skills/` directory. Each skill has a `SKILL.md` file with instructions. Follow the skill's workflow when applicable.
- Before running tests or static analysis, check the actual PHP version in Dockerfile and run commands in the `php-app` container, not with host PHP; see [README.md](README.md#run-tests) for commands.
