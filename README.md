# FileCacher class 
[![Total Downloads](https://poser.pugx.org/dburiy/filecacher/d/total.png)](https://packagist.org/packages/dburiy/filecacher/stats)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
![visitor badge](https://visitor-badge.glitch.me/badge?page_id=dburiy.filecacher)
## Install 
The recommended way to install this package is through Composer:

```
$ composer require dburiy/filecacher
```

## How to use

#### Init
```
$cacher_dir = __DIR__ . '/cacher'; 
$cacher = new \Dburiy\FileCacher($cacher_dir);
```

#### Store data
```
$lifetime = 10; // time in second
$data = "some string";
$cacher->set("storage_strings_str1", $data, $lifetime);

# Result:
# Will be created file in cache dir: `[dir]/storage/strings/str1`
# with data: `some string`
```

#### Get data
```
$default = "some default value";
$callback = function (){
    return time();
};

# if cache not found or expire
$cacher->get("key");            # return null
$cacher->get("key", $default);  # return string "some default value"
$cacher->get("key", $callback); # return timestamp
```

#### Remove data
```
$cacher->delete("key");
```

## With PSR

```
use Dburiy\FileCacher;
use Dburiy\PsrBridge\FileCacher as PsrCacher;

include __DIR__ . '/../vendor/autoload.php';

$cacher = new PsrCacher(new FileCacher(__DIR__ . '/cache'));

$item = $cacher
    ->getItem('test')
    ->expiresAfter(DateInterval::createFromDateString('1 min'))
//    ->expiresAfter(10) // seconds
//    ->expiresAt(new DateTime('2022-01-29T13:02:00', new DateTimeZone('europe/moscow')))
    ->set(['value' => time()])
;
$cacher->save($item);
//$cacher->saveDeferred($item);
//$cacher->commit();

var_dump($item->get());

if (!$item->isHit()) {
    $cacher->deleteItem('test');
}
```


