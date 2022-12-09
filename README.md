# Simple Microblog

A very simple PHP app that stores twitter-like status updates in a sqlite database. It also generates a JSON feed, that can be used as a source for the [micro.blog](https://micro.blog/) service. It is aimed at people who would like to host their own micro.blog, but want to avoid using Wordpress for it.

![a screenshot of the microblog app](https://user-images.githubusercontent.com/1279725/34184164-9567a4b2-e51e-11e7-9317-d737ef3423f0.png)

There is a timeline view of your own posts, as well as a simple 'compose post' page behind a login form. Right now, only a unique ID, the post content and creation timestamp, edit time and delete status are saved for each entry, so this is only suitable for one user. (Multiple users would each have to install in their own directories.)

The entire design is inside a single theme file [microblog.css](microblog.css) and can be modified easily. The site HTML is pretty straightforward and should be easy to style.

ATOM and JSON feeds are provided and rendered as static files when posting.

The app requires at least PHP 5.5 and was tested on 8.1. It needs mbstring, curl and sqlite modules. 
For crossposting to twitter, the app uses code from [J7mbo/twitter-api-php](https://github.com/J7mbo/twitter-api-php)

### Installation

- copy the files to a directory on your webserver
- copy (or rename) [config-dist.php](config-dist.php) to config.php and adjust the settings if you like (at least set a new password!)
- for Apache: edit [.htaccess](.htaccess) and set `RewriteBase` to a path matching your installation directory
- for nginx: have a rule similar to `try_files $uri $uri/ /index.php?$args;` for the microblog-location
- optional: modify the theme file [microblog.css](microblog.css)
- optional: enable crossposting to twitter by filling in app credentials in [config.php](config.php#L32-L35) (instructions there)

### To Do

- test whether the [ping function](http://help.micro.blog/2017/api-feeds/) actually works
- improve html rendering
- maybe improve theming support by adding a themes dir, moving the CSS there and setting theme via config file
- see issues

### Support my work

The app is provided for free, but if you'd like to support what I do, please consider tipping or sponsoring – it is greatly appreciated. Can't promise it'll buy you software support, but if you send a reasonable PR, I'm happy to accept improvements to the app. Links are under GitHub's official sponsor button.
