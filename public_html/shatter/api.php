<?php

$gAppName = "shatterservice";

define("APP_LOADED", 1);

require_once "main.php";

require_once "kshatterserviceutils.php";
require_once "weakuser.php";
require_once "claims.php";

$gEndMan->add("list-bundles", function (Page $page) {
    $page->set("bundles", [
        [
            "name" => "Advanced Level Server",
            "credit" => "yorshex",
            "info" => "Provides an advanced level server",
            "hash" => sha1("invalid_bundle_hash"),
        ]
    ]);
    $page->send();
});

$gEndMan->add("get-bundle", function (Page $page) {
    global $gStorage;
    
    $page->set_mode(PAGE_MODE_RAW);
    $page->type("application/octet-stream");
    $hash = $page->get("hash");
    $filename = "Bundle_$hash." . ($page->has("sigfile") ? "sig" : "zip");
    
    $content = "";
    
    if ($gStorage->has($filename)) {
        $content = $gStorage->load($filename);
    }
    
    $page->force_download($filename);
    $page->add($content);
});

$gEndMan->add("upload-bundle", function (Page $page) {
    $user = user_get_current();
    
    if ($user && $user->is_admin()) {
        if (!$page->has("submit")) {
            $page->set_mode(PAGE_MODE_HTML);
            
            $form = new Form("./api.php?action=upload-bundle&submit=1");
            $form->upload("bundle_file", "Main bundle file", "");
            $form->upload("bundle_sig", "Signature", "");
            $form->submit("Upload bundle");
            
            $page->add($form);
        }
        else {
            global $gStorage;
            
            $file = $page->get_file("bundle_file");
            $sig = $page->get_file("bundle_sig");
            
            $bundle_hash = sha1($file);
            
            $gStorage->save("Bundle_$bundle_hash.zip", $file);
            $gStorage->save("Bundle_$bundle_hash.sig", $sig);
            
            $page->set("status", "success");
            $page->set("message", "The bundle has been uploaded successfully.");
            $page->set("hash", $bundle_hash);
        }
    }
    else {
        $page->info("not_authed", "You are not authorised to submit bundles.");
    }
});

$gEndMan->add("auth-login-ui", function (Page $page) {
    $page->set_mode(PAGE_MODE_HTML);
    KSHeader($page);
    $form = new Form("./api.php?action=auth-login&submit=1");
    $form->textbox("handle", "Handle", "");
    $form->password("password", "Password", "");
    $form->submit("Log in");
    $page->add($form);
    KSFooter($page);
});

$gEndMan->add("auth-register-ui", function (Page $page) {
    $page->set_mode(PAGE_MODE_HTML);
    KSHeader($page);
    $form = new Form("./api.php?action=auth-register&submit=1");
    $form->textbox("email", "Email", "");
    $form->textbox("handle", "Handle", "");
    $form->submit("Register");
    $page->add($form);
    KSFooter($page);
});

$gEndMan->add("user-info-ui", function (Page $page) {
    $page->set_mode(PAGE_MODE_HTML);
    KSHeader($page);
    $user = user_get_current();
    $page->add("ID: $user->id<br/>");
    $page->add("Handle: $user->handle<br/>");
    $page->add("Created: " . date("Y-m-d H:i:s", $user->created) . "<br/>");
    $page->add("Admin: " . (($user->is_admin()) ? "true" : "false"));
    KSFooter($page);
});

$gEndMan->add("user-info", function (Page $page) {
    $current_user = user_get_current();
    
    if ($current_user) {
        $handle = $page->get("handle");
        $user = user_get_from_handle($handle);
        
        if (!$user) {
            $page->info("not_exists", "This user does not exist.");
        }
        
        $page->set("id", $user->id);
        $page->set("display", $user->display);
        $page->set("pronouns", $user->pronouns);
        $page->set("created", $user->created);
        $page->set("roles", $user->roles);
    }
    else {
        $page->info("not_authed", "You do not have permission to preform this action.");
    }
});

$gEndMan->add("colon-three", function (Page $page) {
    $page->set_mode(PAGE_MODE_HTML);
    $page->add("<h1>:3</h1>");
});

kwl_main();
