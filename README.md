# Prestashop Image Optimizer

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.2-green)](https://php.net/)
[![Minimum Prestashop Version](https://img.shields.io/badge/prestashop-%3E%3D%201.7.6.0-green)](https://www.prestashop.com)
[![GitHub release](https://img.shields.io/github/v/release/Pixel-Open/prestashop-image-optimizer)](https://github.com/Pixel-Open/prestashop-image-optimizer/releases)

## Presentation

Image optimizer module is an easy way to resize and compress images on the fly. Use responsive images with size alternatives.

## Requirements

- Prestashop >= 1.7.6.0
- PHP >= 7.2.0

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

#### Optimizer options

- **alt**: alternative text (optional)
- **class**: img element class name (optional)
- **image_name**: image name (optional, keep the same image name if empty)
- **quality**: image quality from 0 to 100 (optional, used only for jpg and webp)
- **width**: maximum width (optional)
- **height**: maximum height (optional)
- **ext**: convert image to jpg, png, gif or webp (optional)
- **breakpoints**: Alternative widths of responsive images in px (e.g. "500,800,1200") (optional)
- **template**: custom template file

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
<img src="https://www.example.com/img/web/my-product-name-750x562-80.jpg"
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
<img src="https://www.example.com/img/web/image-800x600-90.jpg"
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
    breakpoints='500,800'
}
```

Result:

```html
<picture>
    <source media="(max-width: 800px)" srcset="https://www.example.com/img/web/image-800x400-90.jpg" />
    <source media="(max-width: 500px)" srcset="https://www.example.com/img/web/image-500x250-90.jpg" />
    <img src="https://www.example.com/img/web/image-1200x600-90.jpg" loading="lazy" />
</picture>
```

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