# Cover website
This is the source code of the Cover website.

The website mostly uses the Symfony PHP framework, but some components are replaced with custom legacy implementations left over from the olden days.

## License
Note that all the code in this repository is property of Cover. You are allowed to learn from it, play with it and contribute fixes and features to it. You are not allowed to use (parts of) the code or resources (e.g. documents, images) for other projects unrelated to Cover. Unless of course you contributed those parts to this project yourself in the first place.

## Security
Please, if you find a bug or security issue, please report it by making an issue on the Bitbucket repository or by notifying the AC/DCee at webcie@rug.nl

## Contribute
If you want to contribute code please fork this repository, create a new branch in which you implement your fix or feature, make sure it merges with the most up to date code in our master branch. (i.e. Just `git rebase master` when your master branch is in sync.) When the code is ready to be set live create a pull request and the WebCie will test and review your contribution.

## Install
For development, it's easiest to setup a local install of the website. To do so, you can follow the instructions in [quick_install.md](/docs/quick_install.md). This guide has been written for Ubuntu 24.04 (stand alone or on Windows Subsystem for Linux). There's also the [old installation instructions](/docs/install.old.md), which might contain helpful information.

## Documentation
The website is built on Symfony. This repository includes [a short guide on getting started with Symfony](/docs/symfony.md), which also includes some details on the specifics of our code base. Check it out!

Then, there are the undocumented legacy parts that still need to be replaced by Symfony’s alternative. The following components use legacy implementations:

- Authentication in place of Symfony's SecurityBundle
- Policies in place of Symfony's security voters
- Database in place of Symfony's Doctrine ORM pack
- Translations (i18n)

While these components are largely undocumented, their basics are explained in [the legacy documentation](/docs/legacy.md) to help you get started.

### Frontend
The frontend assets are managed by Symfony’s [Webpack Encore](https://symfony.com/doc/current/frontend/encore/index.html). 

Style sheets are written in [SASS](https://sass-lang.com/) and are based on the [Cover Style System](https://bitbucket.org/cover-webcie/cover-style-system) project, which in turn is based on the [Bulma CSS framework](https://bulma.io/). 

Interactive framework components are written in plain JavaScript, without frameworks or derrived languages. Modern JS is quite neat and not as hard as some people think. Again some components are derrived from the [Cover Style System](https://bitbucket.org/cover-webcie/cover-style-system) project, which builds on [BulmaJS](https://vizuaalog.github.io/BulmaJS/). Some other components rely on external packages for their functionality.
