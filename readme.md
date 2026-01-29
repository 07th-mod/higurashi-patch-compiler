# Higurashi Patch Compiler

At the moment higurashi patches are difficult to install or update. You need to download the voices, the PS3 graphics patch, the voice patch and optionally the steam sprites patch. This project aims to do all of that automatically and improve the final patch by deleting all the unnecessary voice files. Our aim is to eventually use this compiler to provide easy-to-install patches for every chapter.

## Known issues

### Windows

- **WARNING**: We use an old version of Symfony which is suceptible to the following vulnerability:
  - On Windows, when an executable file named cmd.exe is located in the current working directory it will be called by the Process class when preparing command arguments, leading to possible hijacking.
  - So before running the patch compiler, **ensure there is no suspicious cmd.exe in the current directory**
  - See here for more details: https://github.com/advisories/GHSA-qq5c-677p-737q
  - If anyone can upgrade and confirm the patch compiler still works with newer Symfony, this warning can be removed
- **WARNING**: Also has issues with the "Windows special character vulnerability" CVE-2026-24739, see below

### Windows special character vulnerability

#### Summary
The Symfony Process component did not correctly treat some characters (notably =) as “special” when escaping arguments on Windows. When PHP is executed from an MSYS2-based environment (e.g. Git Bash) and Symfony Process spawns native Windows executables, MSYS2’s argument/path conversion can mishandle unquoted arguments containing these characters.

This can cause the spawned process to receive corrupted/truncated arguments compared to what Symfony intended.

#### Impact
If an application (or tooling such as Composer scripts) uses Symfony Process to invoke file-management commands (e.g. rmdir, del, etc.) with a path argument containing =, the MSYS2 conversion layer may alter the argument at runtime. In affected setups this can result in operations being performed on an unintended path, up to and including deletion of the contents of a broader directory or drive.

The issue is particularly relevant when untrusted input can influence process arguments (directly or indirectly, e.g. via repository paths, extracted archive paths, temporary directories, or user-controlled configuration).

#### Resolution
Upgrade to a Symfony release that includes the fix from symfony/symfony#63164 (which updates Windows argument escaping to ensure arguments containing = and other MSYS2-sensitive characters are properly quoted/escaped).
The patch for branch 5.4 is available at symfony/symfony@ec154f6

#### Workarounds / Mitigations
Avoid running PHP/your tooling from MSYS2-based shells on Windows; prefer cmd.exe or PowerShell for workflows that spawn native executables.
Avoid passing paths containing = (and similar MSYS2-sensitive characters) to Symfony Process when operating under Git Bash/MSYS2.
Where applicable, configure MSYS2 to disable or restrict argument conversion (e.g. via MSYS2_ARG_CONV_EXCL), understanding this may affect other tooling behavior.


## Prerequisites

- [PHP 7.1+](http://php.net/)
- [Composer](https://getcomposer.org/)
- Windows (only if the chapter has case sensitivity issues)


## Installation

After you have PHP and Composer up and running you just need to install the dependencies.

```
$ composer install
```

The compiler needs the game files as well and a mysql database. Copy `config/local.example.yml` to `config/local.yml` and edit the paths and database connection settings. Then use `data/database.sql` to initialize the database.

## Compiling a patch

Compiling a patch is as easy as running a single command. It will download, unpack and put together all the resources. **Beware that the compilation can easily take about half an hour.**

```
$ php console.php higurashi:make <chapter>
```

Replace `<chapter>` with the name of the chapter you want to compile. For example `onikakushi`.

When the process is finished you can find the patch in `/temp/patch/<chapter>`.
