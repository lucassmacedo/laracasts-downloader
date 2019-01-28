# Mangas Downloader

Download de mangas do site : http://goldenmangas.com/
**Working good at 09/03/2018**

## Description
Sincroniza sua pasta local com o site da goldenmangas, quando há novos capitulos de algum manga que voce já baixou.
Se a sua pasta local estiver vazia, todas os capítulos serão baixadas!

## Requirements
- PHP >= 5.4
- php-cURL
- php-xml
- Composer

## Installation
- Clone this repo to a folder in your machine
- Change your info in .env.example and rename it to .env
- `composer install`
- `php start.php -m "nanatsu-no-taizai"` e pronto! Seu mangá vai ser baixado!

Também funciona no navegador, mas é melhor do cli devido ao feedback instantâneo

    php start.php -m "nanatsu-no-taizai" 
  
  obs: o nome do manga deve ser o exatamente igual ao Slug da página do mangá
  
  ex: http://goldenmangas.com/mangas/nanatsu-no-taizai
  

Autor: @luuckymacedo

Credits: github.com/carlosflorencio/laracasts-downloader
## License
This library is under the MIT License, see the complete license [here](LICENSE)
