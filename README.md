# FileCacher class 
[![Build Status](https://travis-ci.org/dburiy/filecacher.svg?branch=master)](https://travis-ci.org/dburiy/filecacher)

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
$cacher->remove("key");
```

#### Use `cache` method
```
$lifetime = 50;
$data = $cacher->cache('key1', $lifetime, function(){
    return time();
});

# if cache with `key1` exist then return cache 
# else return default value and stored it to cache
```

