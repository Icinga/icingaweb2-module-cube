<!-- {% if index %} -->
# Installing Icinga Cube

The recommended way to install Icinga Cube is to use prebuilt packages for
all supported platforms from our official release repository.
Please follow the steps listed for your target operating system,
which guide you through setting up the repository and installing Icinga Cube.

<!-- {% else %} -->
<!-- {% if not from_source %} -->
## Adding Icinga Package Repository

The recommended way to install Icinga Cube is to use prebuilt packages from our official release repository.

!!! tip

    If you install Icinga Cube on a node that has Icinga 2, Icinga DB or Icinga Web installed via packages,
    proceed to [installing the Icinga Cube package](#installing-icinga-cube-package) as
    the repository is already configured.

Here's how to add the official release repository:

<!-- {% if amazon_linux %} -->
<!-- {% if not icingaDocs %} -->
### Amazon Linux 2 Repository
<!-- {% endif %} -->
!!! info

    A paid repository subscription is required for Amazon Linux 2 repositories. Get more information on
    [icinga.com/subscription](https://icinga.com/subscription).

    Don't forget to fill in the username and password section with appropriate credentials in the local .repo file.

```bash
rpm --import https://packages.icinga.com/icinga.key
wget https://packages.icinga.com/subscription/amazon/ICINGA-release.repo -O /etc/yum.repos.d/ICINGA-release.repo
```
<!-- {% endif %} -->

<!-- {% if centos %} -->
<!-- {% if not icingaDocs %} -->
### CentOS Repository
<!-- {% endif %} -->
```bash
rpm --import https://packages.icinga.com/icinga.key
wget https://packages.icinga.com/centos/ICINGA-release.repo -O /etc/yum.repos.d/ICINGA-release.repo
```
<!-- {% endif %} -->

<!-- {% if debian %} -->
<!-- {% if not icingaDocs %} -->
### Debian Repository
<!-- {% endif %} -->

```bash
apt-get update
apt-get -y install apt-transport-https wget gnupg

wget -O - https://packages.icinga.com/icinga.key | apt-key add -

DIST=$(awk -F"[)(]+" '/VERSION=/ {print $2}' /etc/os-release); \
 echo "deb https://packages.icinga.com/debian icinga-${DIST} main" > \
 /etc/apt/sources.list.d/${DIST}-icinga.list
 echo "deb-src https://packages.icinga.com/debian icinga-${DIST} main" >> \
 /etc/apt/sources.list.d/${DIST}-icinga.list

apt-get update
```
<!-- {% endif %} -->

<!-- {% if rhel %} -->
<!-- {% if not icingaDocs %} -->
### RHEL Repository
<!-- {% endif %} -->
!!! info

    A paid repository subscription is required for RHEL repositories. Get more information on
    [icinga.com/subscription](https://icinga.com/subscription).

    Don't forget to fill in the username and password section with appropriate credentials in the local .repo file.

```bash
rpm --import https://packages.icinga.com/icinga.key
wget https://packages.icinga.com/subscription/rhel/ICINGA-release.repo -O /etc/yum.repos.d/ICINGA-release.repo
```
<!-- {% endif %} -->

<!-- {% if sles %} -->
<!-- {% if not icingaDocs %} -->
### SLES Repository
<!-- {% endif %} -->
!!! info

    A paid repository subscription is required for SLES repositories. Get more information on
    [icinga.com/subscription](https://icinga.com/subscription).

    Don't forget to fill in the username and password section with appropriate credentials in the local .repo file.

```bash
rpm --import https://packages.icinga.com/icinga.key

zypper ar https://packages.icinga.com/subscription/sles/ICINGA-release.repo
zypper ref
```
<!-- {% endif %} -->

<!-- {% if ubuntu %} -->
<!-- {% if not icingaDocs %} -->
### Ubuntu Repository
<!-- {% endif %} -->

```bash
apt-get update
apt-get -y install apt-transport-https wget gnupg

wget -O - https://packages.icinga.com/icinga.key | apt-key add -

. /etc/os-release; if [ ! -z ${UBUNTU_CODENAME+x} ]; then DIST="${UBUNTU_CODENAME}"; else DIST="$(lsb_release -c| awk '{print $2}')"; fi; \
 echo "deb https://packages.icinga.com/ubuntu icinga-${DIST} main" > \
 /etc/apt/sources.list.d/${DIST}-icinga.list
 echo "deb-src https://packages.icinga.com/ubuntu icinga-${DIST} main" >> \
 /etc/apt/sources.list.d/${DIST}-icinga.list

apt-get update
```
<!-- {% endif %} -->

## Installing Icinga Cube Package

Use your distribution's package manager to install the `icinga-cube` package as follows:

<!-- {% if amazon_linux %} -->
<!-- {% if not icingaDocs %} -->
#### Amazon Linux 2
<!-- {% endif %} -->
```bash
yum install icinga-cube
```
<!-- {% endif %} -->

<!-- {% if centos %} -->
<!-- {% if not icingaDocs %} -->
#### CentOS
<!-- {% endif %} -->
!!! info

    Note that installing Icinga Cube is only supported on CentOS 7 as CentOS 8 is EOL.

```bash
yum install icinga-cube
```
<!-- {% endif %} -->

<!-- {% if debian or ubuntu %} -->
<!-- {% if not icingaDocs %} -->
#### Debian / Ubuntu
<!-- {% endif %} -->
```bash
apt-get install icinga-cube
```
<!-- {% endif %} -->

<!-- {% if rhel %} -->
#### RHEL 8 or Later

```bash
dnf install icinga-cube
```

#### RHEL 7

```bash
yum install icinga-cube
```
<!-- {% endif %} -->

<!-- {% if sles %} -->
<!-- {% if not icingaDocs %} -->
#### SLES
<!-- {% endif %} -->
```bash
zypper install icinga-cube
```
<!-- {% endif %} -->

<!-- {% else %} --><!-- {# end if not from_source #} -->
<!-- {% if not icingaDocs %} -->
## Installing Icinga Cube from Source
<!-- {% endif %} -->

Please see the Icinga Web documentation on
[how to install modules](https://icinga.com/docs/icinga-web-2/latest/doc/08-Modules/#installation) from source.
Make sure you use `cube` as the module name. The following requirements must also be met.

### Requirements

* PHP (≥7.2)
* [Icinga Web](https://github.com/Icinga/icingaweb2) (≥2.9)
* [Icinga DB Web](https://github.com/Icinga/icingadb-web) (≥1.0)
* [Icinga PHP Library (ipl)](https://github.com/Icinga/icinga-php-library) (≥0.9)

If you are using PostgreSQL, you need at least 9.5 which provides the `ROLLUP` feature.
<!-- {% endif %} --><!-- {# end else if not from_source #} -->

## Configuring Icinga Cube

<!-- {% if not from_source %} -->
The Icinga Web PHP framework is required to configure and run the Icinga Cube.
Package installations of `icinga-cube` already set up the necessary dependencies.

If Icinga Web has not been installed or set up before,
you have completed the instructions here and can proceed to
<!-- {% if amazon_linux %} -->
[install the web server on Amazon Linux](https://icinga.com/docs/icinga-web-2/latest/doc/02-Installation/06-Amazon-Linux/#install-the-web-server),
<!-- {% endif %} -->
<!-- {% if centos %} -->
[install the web server on CentOS](https://icinga.com/docs/icinga-web-2/latest/doc/02-Installation/03-CentOS/#install-the-web-server),
<!-- {% endif %} -->
<!-- {% if debian %} -->
[install the web server on Debian](https://icinga.com/docs/icinga-web-2/latest/doc/02-Installation/01-Debian/#install-the-web-server),
<!-- {% endif %} -->
<!-- {% if rhel %} -->
[install the web server on RHEL](https://icinga.com/docs/icinga-web-2/latest/doc/02-Installation/04-RHEL/#install-the-web-server),
<!-- {% endif %} -->
<!-- {% if sles %} -->
[install the web server on SLES](https://icinga.com/docs/icinga-web-2/latest/doc/02-Installation/05-SLES/#install-the-web-server),
<!-- {% endif %} -->
<!-- {% if ubuntu %} -->
[install the web server on Ubuntu](https://icinga.com/docs/icinga-web-2/latest/doc/02-Installation/02-Ubuntu/#install-the-web-server),
<!-- {% endif %} -->
which will then take you to the web-based setup wizard, which also allows you to enable the Icinga Cube.
<!-- {% endif %} --><!-- {# end if not from_source #} -->

For Icinga Web setups already running, log in to Icinga Web with a privileged user and enable the Icinga Cube.
<!-- {% endif %} --><!-- {# end else if index #} -->
