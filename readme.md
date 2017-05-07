# Higurashi Patch Compiler

At the moment higurashi patches are difficult to install or update. You need to download the voices, the PS3 graphics patch, the voice patch and optionally the steam sprites patch. This project aims to do all of that automatically and improve the final patch by deleting all the unnecessary voice files. Our aim is to eventually use this compiler to provide easy-to-install patches for every chapter.

## Prerequisites

- [PHP 7.1+](http://php.net/)
- [Composer](https://getcomposer.org/)
- Windows (unless the chapter is free of case sensitivity issues)

## Installation

After you have PHP and Composer up and running you just need to install the dependencies.

```
$ composer install
```

The compiler needs the game files as well. Please check that the paths in `Constants.php` are correct. (TODO: Add a local YAML file instead.)

## Compiling a patch

Compiling a patch is as easy as running a single command. It will download, unpack and put together all the resources. **Beware that the compilation can easily take about half an hour.**

```
$ php console.php higurashi:make <chapter>
```

Replace `<chapter>` with the name of the chapter you want to compile. For example `onikakushi`.

When the process is finished you can find the patch in `/temp/patch/<chapter>`.
