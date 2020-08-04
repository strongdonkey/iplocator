# IP地址转地理位置工具
###数据采用纯真数据，http://www.cz88.net/ip，表示感谢
###当前数据库版本：2020-07-30

```php
require_once 'vendor/autoload.php';

use Strongdonkey\IPLocator\Locator;

var_dump(Locator::find('52.206.227.240'));

//返回值
array(4) {
  ["beginip"]=>
  string(10) "52.200.0.0"     //当前IP段起始
  ["endip"]=>
  string(14) "52.207.255.255" //当前IP段结束
  ["country"]=>
  string(6) "美国"            //国家或地区
  ["area"]=>
  string(42) "弗吉尼亚州阿什本Amazon数据中心" //所属单位或运营商
}
```