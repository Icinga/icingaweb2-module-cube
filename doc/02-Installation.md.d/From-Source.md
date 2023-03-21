# Installing Icinga Cube from Source

Please see the Icinga Web documentation on
[how to install modules](https://icinga.com/docs/icinga-web-2/latest/doc/08-Modules/#installation) from source.
Make sure you use `cube` as the module name. The following requirements must also be met.

## Requirements

* PHP (≥7.2)
* [Icinga Web](https://github.com/Icinga/icingaweb2) (≥2.9)
* [Icinga DB Web](https://github.com/Icinga/icingadb-web) (≥1.0)
* [Icinga PHP Library (ipl)](https://github.com/Icinga/icinga-php-library) (≥0.11.0)

If you are using PostgreSQL, you need at least version 9.5 which provides the `ROLLUP` feature.
<!-- {% include "02-Installation.md" %} -->
