# HTML presenter

A small PHP app for presenting HTML pages to customers. Drop your pages in a folder, share one link, and they open in a viewer with desktop, tablet and phone previews and section-by-section navigation.

## Requirements

- PHP 8+ with sessions and the `dom` extension (both enabled by default)
- A web server such as Apache or Nginx
- Internet access for the viewer (it loads a font from Google Fonts)

## Installation

1. Upload this folder to your PHP server.
2. Put your `.html` files (plus their images/CSS) in `slides/`. Delete the two demo files.
3. Open the folder URL, e.g. `https://yoursite.com/presenter/`, and send it to the customer.

Apache works out of the box thanks to `slides/.htaccess`. On Nginx, add this to block direct access to the pages (adjust `/presenter/` to your path):

    location ~* ^/presenter/slides/.*\.(html?|php)$ { deny all; }
