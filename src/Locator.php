<?php
/**
 * A Tool for convert IP address to location
 * User: strongdonkey <405387525@qq.com>
 * https://github.com/strongdonkey/iplocator
 */

namespace Strongdonkey\IPLocator;

class Locator
{
    private static $fh;
    private static $first;
    private static $last;
    private static $total;
    private static $dbcharset = 'gb2312';
    private static $dbname = 'qqwry.dat';

    //Init
    private static function init()
    {
        $dbfile = dirname(__FILE__).'/../db/' . self::$dbname;

        if (!file_exists($dbfile)) {
            throw new \Exception('db file not found');
        }

        echo $dbfile;

        self::$fh = fopen($dbfile, 'rb');
        self::$first = self::getLong4();
        self::$last = self::getLong4();
        self::$total = (self::$last - self::$first) / 7; //每条索引7字节
    }

    /**
     * 获取IP地址主函数
     * @param string $ip
     * @param string $charset
     * @return bool | array
     */
    public static function find($ip, $charset='utf-8')
    {
        if ( ! self::$fh) {
            self::init();
        }

        if ( ! self::checkIp($ip)) {
            return false;
        }

        $ip = pack('N', intval(ip2long($ip)));

        //二分查找
        $l = 0;
        $r = self::$total;

        $findip = 0;

        while ($l <= $r) {
            $m = floor(($l + $r) / 2); //计算中间索引
            fseek(self::$fh, self::$first + $m * 7);
            $beginip = strrev(fread(self::$fh, 4)); //中间索引的开始IP地址
            fseek(self::$fh, self::getLong3());
            $endip = strrev(fread(self::$fh, 4)); //中间索引的结束IP地址

            if ($ip < $beginip) { //用户的IP小于中间索引的开始IP地址时
                $r = $m - 1;
            } else {
                if ($ip > $endip) { //用户的IP大于中间索引的结束IP地址时
                    $l = $m + 1;
                } else { //用户IP在中间索引的IP范围内时
                    $findip = self::$first + $m * 7;
                    break;
                }
            }
        }

        //查询国家地区信息
        fseek(self::$fh, $findip);
        $location['beginip'] = long2ip(self::getLong4()); //用户IP所在范围的开始地址
        $offset = self::getlong3();
        fseek(self::$fh, $offset);
        $location['endip'] = long2ip(self::getLong4()); //用户IP所在范围的结束地址
        $byte = fread(self::$fh, 1); //标志字节
        switch (ord($byte)) {
            case 1:  //国家和区域信息都被重定向
                $countryOffset = self::getLong3(); //重定向地址
                fseek(self::$fh, $countryOffset);
                $byte = fread(self::$fh, 1); //标志字节
                switch (ord($byte)) {
                    case 2: //国家信息被二次重定向
                        fseek(self::$fh, self::getLong3());
                        $location['country'] = self::getInfo();
                        fseek(self::$fh, $countryOffset + 4);
                        $location['area'] = self::getArea();
                        break;
                    default: //国家信息没有被二次重定向
                        $location['country'] = self::getInfo($byte);
                        $location['area'] = self::getArea();
                        break;
                }
                break;

            case 2: //国家信息被重定向
                fseek(self::$fh, self::getLong3());
                $location['country'] = self::getInfo();
                fseek(self::$fh, $offset + 8);
                $location['area'] = self::getArea();
                break;

            default: //国家信息没有被重定向
                $location['country'] = self::getInfo($byte);
                $location['area'] = self::getArea();
                break;
        }

        //gb2312 to utf-8（去除无信息时显示的CZ88.NET）
        foreach ($location as $k => $v) {

            if ($charset != self::$dbcharset) {
                $v = iconv(self::$dbcharset, $charset, $v);
            }

            $location[$k] = str_replace('CZ88.NET', '', $v);

        }

        return $location;
    }

    //检查IP合法性
    private static function checkIp($ip)
    {
        $ip = filter_var($ip, FILTER_VALIDATE_IP);

        if ($ip === false) {
            return false;
        }

        return true;
    }

    private static function getLong4()
    {
        //读取little-endian编码的4个字节转化为长整型数
        $result = unpack('Vlong', fread(self::$fh, 4));
        return $result['long'];
    }

    private static function getLong3()
    {
        $result = unpack('Vlong', fread(self::$fh, 3).chr(0));
        return $result['long'];
    }

    private static function getInfo($data = "")
    {
        $char = fread(self::$fh, 1);
        
        while (ord($char) != 0) { //国家地区信息以0结束
            $data .= $char;
            $char = fread(self::$fh, 1);
        }
        
        return $data;
    }

    //查询地区信息
    private static function getArea()
    {
        $byte = fread(self::$fh, 1); //标志字节

        switch (ord($byte)) {
            case 0: $area = ''; break; //没有地区信息
            case 1: //地区被重定向
                fseek(self::$fh, self::getLong3());
                $area = self::getInfo(); break;
            case 2: //地区被重定向
                fseek(self::$fh, self::getLong3());
                $area = self::getInfo(); break;
            default: $area = self::getInfo($byte);  break; //地区没有被重定向
        }
        return $area;
    }

    public function __destruct()
    {
        fclose(self::$fh);
    }
}
