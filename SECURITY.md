# Security

Do not expose temporary runtime validation scripts through a public web root. Remove them after testing.

Report security issues privately to the repository maintainers rather than opening a public issue with exploit details.

Add-ons remain responsible for authorization, input validation, output escaping, and safe use of native vBulletin APIs. The runtime does not provide Laravel Auth, a permission system, or automatic request validation.
