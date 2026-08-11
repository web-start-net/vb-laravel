# Compatibility

## Runtime versions

Runtime 0.x is experimental. Necessary changes may still occur before 1.0, but proven public APIs should not be broken without a clear reason.

Runtime 1.x and later will preserve documented Runtime and Bridge APIs within the same major version wherever practical. The preferred sequence is:

```text
add → deprecate → remove only in a major release
```

vBulletin compatibility differences should be absorbed inside Runtime or Bridge wherever practical. Illuminate upgrades are owned by VB Laravel Runtime maintainers, not by every add-on developer.

## Add-on compatibility

Add-ons target the runtime with a manifest constraint such as `"runtime": "^0.1"`. They must not target a specific internal Illuminate version or ship another framework copy.

## Tested environment

The 0.1.0 package was validated against a real isolated vBulletin 6.2.2 installation on PHP 8.2+ with MySQL. Other vBulletin/PHP combinations require their own validation.
