<!-- {% if index %} -->
# Installing Icinga Cube

The recommended way to install Icinga Cube is to use prebuilt packages for
all supported platforms from our official release repository.
Please note that [Icinga Web](https://icinga.com/docs/icinga-web) is required to run Icinga Cube
and if it is not already set up, it is best to do this first.

The following steps will guide you through installing and setting up Icinga Cube.
<!-- {% else %} -->
<!-- {% if not icingaDocs %} -->

## Installing the Package

If the [repository](https://packages.icinga.com) is not configured yet, please add it first.
Then use your distribution's package manager to install the `icinga-cube` package
or install [from source](02-Installation.md.d/From-Source.md).
<!-- {% endif %} --><!-- {# end if not icingaDocs #} -->

## Configuring Icinga Cube

No additional steps are required to set up Icinga Cube and it is ready to use right after installation.
<!-- {% endif %} --><!-- {# end else if index #} -->
