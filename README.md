# Icinga Cube

[![PHP Support](https://img.shields.io/badge/php-%3E%3D%207.2-777BB4?logo=PHP)](https://php.net/)
![Build Status](https://github.com/icinga/icingaweb2-module-cube/workflows/PHP%20Tests/badge.svg?branch=main)
[![Github Tag](https://img.shields.io/github/tag/Icinga/icingaweb2-module-cube.svg)](https://github.com/Icinga/icingaweb2-module-cube)

![Icinga Logo](https://icinga.com/wp-content/uploads/2014/06/icinga_logo.png)

The Icinga Cube is a tiny but useful [Icinga Web](https://github.com/Icinga/icingaweb2)
module. It currently shows host and service statistics (total count, health) grouped by
various custom variables in multiple dimensions.

![Cube - Overview](doc/img/cube_simple.png)

It will be your new best friend in case you are running a large environment and
want to get a quick answers to questions like:

* Which project uses how many servers per environment at which location/site?
  * Who occupies most servers?
  * How many of those are used in production?
  * Which project has only development and test boxes? 
* Which operating system is used for which project and in which environment?
  * Do we still have Debian Lenny?
  * Which projects are to blame for this?
  * Do we have applications where the operating systems used differ in staging
    and production? 
* Which project uses which operating system version for which application?
  * Which projects have homogeneous environments?
  * Which projects are at a consistent patch level?
  * How many RHEL 6 variants (6.1, 6.2, 6.3...) do we use?
  * Who is running the oldest ones? In production?
* Which projects are still using physical servers in which environment?

For Businessmen - Drill and Slice
---------------------------------

Get answers to your questions. Quick and fully autonomous, using the cube
requires no technical skills. Choose amongst all available dimensions and rotate
the Cube to fit your needs.

![Cube - Configure Dimensions](doc/img/cube_move-up.png)

Want to drill down? Choose a slice and get your answers:

![Cube - Configure Dimensions](doc/img/cube_slice.png)

All facts configured for systems monitored by [Icinga](https://www.icinga.com/)
can be used for your research.

For Icinga Director users
-------------------------

In case you are using the [Icinga Director](https://github.com/Icinga/icingaweb2-module-director),
in addition to the multi-selection/edit feature the cube provides a nice way to
modify multiple hosts at once.

![Cube - Director multi-edit](doc/img/cube_director.png)

Installation
------------

To install Icinga Cube see [Installation](https://icinga.com/docs/icinga-cube/latest/doc/02-Installation/).
