<?php

namespace Dizatech\PasargadIpg;

define("BCCOMP_LARGER", 1);

class Rsa{
    const XMLFile = 0;
    const XMLString = 1;
    private $public_key = null;
    private $private_key = null;
    private $modulus = null;
    private $key_length = 1024;

    public function __construct($xmlRsakey=null)
    {
        $xmlObj = null;
        $xmlObj = simplexml_load_string($xmlRsakey);
        $this->modulus = $this->binary_to_number(base64_decode($xmlObj->Modulus));
        $this->public_key = $this->binary_to_number(base64_decode($xmlObj->Exponent));
        $this->private_key = $this->binary_to_number(base64_decode($xmlObj->D));
        $this->key_length = strlen(base64_decode($xmlObj->Modulus))*8;
    }
    
    function sign($message) 
    {
        $padded = $this->add_PKCS1_padding($message, false, $this->key_length / 8);
        $number = $this->binary_to_number($padded);
        $signed = $this->pow_mod($number, $this->private_key, $this->modulus);
        $result = $this->number_to_binary($signed, $this->key_length / 8);
        return $result;
    }

    function pow_mod($p, $q, $r) 
    {
        $factors = array();
        $div = $q;
        $power_of_two = 0;
        while (bccomp($div, "0") == BCCOMP_LARGER){
            $rem = bcmod($div, 2);
            $div = bcdiv($div, 2);
            if($rem) array_push($factors, $power_of_two);
            $power_of_two++;
        }
        $partial_results = array();
        $part_res = $p;
        $idx = 0;
        foreach ($factors as $factor){
            while ($idx < $factor){
                $part_res = bcpow($part_res, "2");
                $part_res = bcmod($part_res, $r);
                $idx++;
            }
            array_push($partial_results, $part_res);
        }
        $result = "1";
        foreach ($partial_results as $part_res){
            $result = bcmul($result, $part_res);
            $result = bcmod($result, $r);
        }
        return $result;
    }

    function add_PKCS1_padding($data, $isPublicKey, $blocksize)
    {
        $pad_length = $blocksize - 3 - strlen($data);
        $block_type = "\x01";
        $padding = str_repeat("\xFF", $pad_length);
        return "\x00" . $block_type . $padding . "\x00" . $data;
    }

    function binary_to_number($data)
    {
        $base = "256";
        $radix = "1";
        $result = "0";
        for ($i = strlen($data) - 1; $i >= 0; $i--){
            $digit = ord($data[$i]);
            $part_res = bcmul($digit, $radix);
            $result = bcadd($result, $part_res);
            $radix = bcmul($radix, $base);
        }
        return $result;
    }

    function number_to_binary($number, $blocksize)
    {
        $base = "256";
        $result = "";
        $div = $number;
        while ($div > 0){
            $mod = bcmod($div, $base);
            $div = bcdiv($div, $base);
            $result = chr($mod) . $result;
        }
        return str_pad($result, $blocksize, "\x00", STR_PAD_LEFT);
    }
}
