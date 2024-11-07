# Higurashi Patch Compiler

At the moment higurashi patches are difficult to install or update. You need to download the voices, the PS3 graphics patch, the voice patch and optionally the steam sprites patch. This project aims to do all of that automatically and improve the final patch by deleting all the unnecessary voice files. Our aim is to eventually use this compiler to provide easy-to-install patches for every chapter.

## Prerequisites

- [PHP 7.1+](http://php.net/)
- [Composer](https://getcomposer.org/)
- Windows (only if the chapter has case sensitivity issues)
    - **WARNING**: We use an old version of Symfony which is suceptible to the following vulnerability:
      - On Windows, when an executable file named cmd.exe is located in the current working directory it will be called by the Process class when preparing command arguments, leading to possible hijacking.
      - So before running the patch compiler, **ensure there is no suspicious cmd.exe in the current directory**
      - See here for more details: https://github.com/advisories/GHSA-qq5c-677p-737q
      - If anyone can upgrade and confirm the patch compiler still works with newer Symfony, this warning can be removed

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
