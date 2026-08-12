# Contributing

Keep changes small and compatible with the frozen 0.1 architecture.

- Do not modify or vendor the vBulletin core.
- Keep the public API limited to documented Runtime and Bridge methods.
- Add native vBulletin lifecycle coverage for integration changes.
- Do not add Laravel subsystems that are outside the current scope.
- Run the regression checks before submitting a change.

Keep the public repository focused on the Runtime and runtime-focused regression coverage. Product/add-on implementations belong in their own repositories.
