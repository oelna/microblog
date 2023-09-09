# Simple Microblog

A simple PHP app that stores Twitter-like status updates in a sqlite database. It also generates a JSON feed, that can be used as a source for the [micro.blog](https://micro.blog/) service. It is aimed at people who would like to host their own micro.blog, but want to avoid using Wordpress for it.

![a screenshot of the microblog app](https://user-images.githubusercontent.com/1279725/34184164-9567a4b2-e51e-11e7-9317-d737ef3423f0.png)

There is a timeline view of your own posts, as well as a simple 'compose post' page behind a login form. Right now, only a unique ID, the post content and creation timestamp, edit time and delete status are saved for each entry, so this is only suitable for one user. (Multiple users would each have to install in their own directories.)

The entire design is inside a theme directory and [CSS file](css/microblog/microblog.css) and can be modified easily. The site HTML is pretty straightforward and should be easy to style.

ATOM and JSON feeds are provided and rerendered as static files when posting.

If the PHP version on the server supports it, an XML-RPC interface is provided to enable posting from external apps, such as [Marsedit](https://redsweater.com/marsedit/). Please set an `app_token` in [config.php](config-dist.php#L28) as secret to use with your username. If you don't set one, you have to use your login password to authenticate. You can use the metaWeblog API, that is discovered automatically, or add a Micro.Blog account and point it to your site. That will use the superior API that includes pagination support. As a bonus, you can schedule posts this way, if you set the creation date in the future ;)

The app requires at least PHP 7.2 and was tested on 8.1. It needs mbstring, curl and sqlite modules.

### Installation

- copy (or clone) the files to a directory on your webserver
- have them accessible from the domain (or subdomain) root `/`
- for Apache: edit [.htaccess](.htaccess) and set `RewriteBase` to a path matching your installation directory
- for nginx: have a rule similar to `try_files $uri $uri/ /index.php?$args;` for the microblog-location
- open the website in the browser, it should prompt you to enter a password for the user 'admin'

### Optional

- modify the theme file [microblog.css](css/microblog/microblog.css)
- do more configuration under `/settings`, eg. fill in ActivityPub info
- set an `app_token` in [config.php](config-dist.php#L28) to use with XML-RPC

### To Do

- test whether the [ping function](http://help.micro.blog/2017/api-feeds/) actually works
- improve html rendering (?)
- support file attachments (started! can attach images to posts and remove them)
- see issues

### Support my work

The app is provided for free, but if you'd like to support what I do, please consider tipping or sponsoring – it is greatly appreciated. Can't promise it'll buy you software support, but if you send a reasonable PR, I'm happy to accept improvements to the app. Links are under GitHub's official sponsor button.
