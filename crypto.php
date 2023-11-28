<?php

function random_base32(int $nchar) : string {
	$alphabet = "0123456789abcdefghijklmnopqrstuv";
	
	$base = random_bytes($nchar);
	$name = "";
	
	for ($i = 0; $i < strlen($base); $i++) {
		$name .= $alphabet[ord($base[$i]) & 0b11111];
	}
	
	return $name;
}

function random_base64(int $nchar) : string {
	$alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_";
	
	$base = random_bytes($nchar);
	$name = "";
	
	for ($i = 0; $i < strlen($base); $i++) {
		$name .= $alphabet[ord($base[$i]) & 0b111111];
	}
	
	return $name;
}

function random_password() : string {
	/**
	 * Randomly generates a new password.
	 */
	
	$alphabet = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!@#$%^&*-_+=[]{}<>()";
	
	$pw = random_bytes(25);
	
	for ($i = 0; $i < strlen($pw); $i++) {
		$pw[$i] = $alphabet[floor((ord($pw[$i]) / 255) * strlen($alphabet))];
	}
	
	return $pw;
}
