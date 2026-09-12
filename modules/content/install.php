<?php
// نصب ماژول محتوا
return function($pdo, $mm) {
    $mm->register('content', 'مدیریت محتوا', 'ایجاد و مدیریت مقالات، صفحات و محصولات', '1.0.0');
    return true;
};
