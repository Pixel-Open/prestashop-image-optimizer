# Prestashop Image Optimizer

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.1-green)](https://php.net/)
[![Minimum Prestashop Version](https://img.shields.io/badge/prestashop-%3E%3D%208.0-green)](https://www.prestashop.com)
[![GitHub release](https://img.shields.io/github/v/release/Pixel-Open/prestashop-image-optimizer)](https://github.com/Pixel-Open/prestashop-image-optimizer/releases)

## Presentation

Image optimizer module is an easy way to resize and compress images on the fly. Use responsive images with size alternatives.

## Requirements

- Prestashop >= 8.0
- PHP >= 8.1
- GD extension (with WebP or AVIF support to convert images to these formats)

## Installation

Download the **pixel_image_optimizer.zip** file from the [last release](https://github.com/Pixel-Open/prestashop-image-optimizer/releases/latest) assets.

### Admin

Go to the admin module catalog section and click **Upload a module**. Select the downloaded zip file.

### Manually

Move the downloaded file in the Prestashop **modules** directory and unzip the archive. Go to the admin module catalog section and search for "Image Optimizer".

## Widget

```html
{widget name='pixel_image_optimizer'}
```

### Options

#### The image to optimize

- **id_image**: the prestashop image id (ex: 1)

```smarty
{widget name='pixel_image_optimizer' id_image=1}
```

- **image_path**: the image path (ex: img/cms/image.jpg)

```smarty
{widget name='pixel_image_optimizer' image_path='img/cms/image.jpg'}
```

- **image_url**: the image URL, absolute on the shop domain or relative (ex: /img/cms/image.jpg). Useful when a module only gives an URL (page builders, uploads...)

```smarty
{widget name='pixel_image_optimizer' image_url=$block.settings.image.url}
```

The image must be inside the shop directory. When it can not be resized (SVG, unwritable cache...), the original image is displayed.

#### Optimizer options

- **alt**: alternative text (optional)
- **class**: img element class name (optional)
- **image_name**: image name (optional, keep the same image name if empty)
- **quality**: image quality from 0 to 100 (optional, default 85, used only for jpg, webp and avif)
- **width**: maximum width (optional)
- **height**: maximum height (optional)
- **ext**: convert image to jpg, png, gif, webp or avif (optional)
- **breakpoints**: alternative widths of responsive images in px (e.g. "500,800,1200") (optional)
- **sizes**: the `sizes` attribute of responsive images (optional, default "100vw")
- **loading**: `lazy` or `eager` (optional, default `lazy`, use `eager` for the main image of the page)
- **fetchpriority**: `high`, `low` or `auto` (optional)
- **template**: custom template file

Images are never upscaled, and the EXIF orientation of JPEG images is applied. The file names contain a hash of the source path and modification time: replacing an image generates new files.

### Examples

#### Product image

```smarty
{foreach $product.images as $image}
    {widget name='pixel_image_optimizer'
        id_image=$image.id_image
        image_name=$product.name
        alt=$image.legend
        class="product-image"
        quality=80
        width=750
    }
{/foreach}
```

Result:

```html
<img src="https://www.example.com/img/web/12-my-product-name-750x562-80-1a2b3c4d.jpg"
     alt="Legend"
     class="product-image"
     width="750"
     height="562"
     loading="lazy" />
```

#### Simple image

```smarty
{widget name='pixel_image_optimizer'
    image_path='img/cms/image.jpg'
    quality=90
    height=600
}
```

Result:

```html
<img src="https://www.example.com/img/web/image-800x600-90-1a2b3c4d.jpg"
     alt=""
     width="800"
     height="600"
     loading="lazy" />
```

#### Responsive image

```smarty
{widget name='pixel_image_optimizer'
    image_path='img/cms/image.jpg'
    quality=90
    width=1200
    ext='webp'
    breakpoints='500,800'
    sizes='(min-width: 992px) 50vw, 100vw'
}
```

Result:

```html
<img src="https://www.example.com/img/web/image-1200x600-90-1a2b3c4d.webp"
     srcset="https://www.example.com/img/web/image-500x250-90-1a2b3c4d.webp 500w,
             https://www.example.com/img/web/image-800x400-90-1a2b3c4d.webp 800w,
             https://www.example.com/img/web/image-1200x600-90-1a2b3c4d.webp 1200w"
     sizes="(min-width: 992px) 50vw, 100vw"
     alt=""
     width="1200"
     height="600"
     loading="lazy" />
```

The browser picks the best file for the displayed size and the screen density.

### Custom template

You can create your own template to display image.

Create a new template file in the **pixel_image_optimizer** directory for your theme:

*themes/{themeName}/modules/pixel_image_optimizer/custom.tpl*

Add the **template** option in the widget with the template path:

```smarty
{widget name='pixel_image_optimizer'
    image_path='img/cms/image.jpg'
    width=1200
    template='module:pixel_image_optimizer/custom.tpl'
}
```

Available variables: `$image` (`url`, `path`, `width`, `height`), `$sources` (alternative sizes, by ascending width, same keys), `$srcset`, `$sizes`, `$alt`, `$class`, `$loading`, `$fetchpriority`.

## Clear image cache

Manually remove the `img/web` directory content, or use the **Clear Image Cache** button from admin:

*Advanced Parameters > Performance > Clear Image Cache*