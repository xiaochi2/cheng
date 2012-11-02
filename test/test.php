<?php

require 'lib/test.php'; // test functions

require_once APP_ROOT . 'lib/function.php';
require_once APP_ROOT . 'lib/autoload.php';
include_once APP_ROOT . 'config/common.php';

Pdb::setConfig($config['db']);

// clear side effects for all
$clear = 1;
if ($clear) {

    // unset all session
    if (1) {
        session_start();
        foreach ($_SESSION as $key => $value) {
            unset($_SESSION[$key]);
        }
    }
    
    // clear user
    Pdb::del(User::$table, array("name LIKE '%test%'" => null));

    // clear customer
    clear_db(Customer::$table, User::$table, 'user');

    // clear user (admin)
    $username = 'test_admin';
    Pdb::del(User::$table, array('name=?' => $username));

    // clear product
    Pdb::del(Product::$table, array('name LIKE ?' => '%_test'));

    // clear cart and order
    clear_db(Cart::$table, Customer::$table, 'customer', 'customer');
    clear_db(Order::$table, Customer::$table, 'customer'); // what if Order submited??

    // clear 
    clear_db('big_to_small_order', Order::$table, 'small', 'small');
    $big_order_ids = Pdb::fetchAll('id', BigOrder::$table);
    if ($big_order_ids) 
        foreach ($big_order_ids as $id)
            if (!Pdb::exists('big_to_small_order', array('big=?' => $id)))
                Pdb::del(BigOrder::$table, array('id=?' => $id));

    // clear address
    clear_relation_db(Customer::$table, Address::$table);

    if (_get('exit')) {
        echo '<script src="static/hide.js"></script>';
        echo '<div class="conclusion pass">All Clear!</div>';
        exit;
    }
}

$all_pass = true;

// case 1 autoload
begin_test();
$id = 101;
$model = new Model($id);
test($model->id, $id, array(
    'name' => 'autoload'));

// case 2 test for kind_of_equal()
begin_test();
$a = array('z' => 3, 'a' => 42);
$b = array('a' => 42, 'z' => 3);
test(kind_of_equal($a, $b), true, array('name' => 'test for kind_of_equal()'));

// case 3 register Customer, user db
begin_test();
$username = 'test_user';
$password = 'password';
$realname = '小池';
$phone = '13711231212';
$email = 'cumt.xiaochi@gmail.com';
$customer = Customer::register(
    compact(
        'username',
        'password',
        'realname',
        'phone',
        'email'));
test(
    1, 
    1, 
    array('name' => 'register Customer, db'));

// case 4 Super Admin create Admin, db
begin_test();
$username = 'test_admin';
$password = 'password';
$user = User::getByName('root');
$superadmin = $user->instance();
$admin = $superadmin->createAdmin(compact('username', 'password'));
$ideal_arr = array(
    'name' => $username,
    'password' => md5($password),
    'type' => 'Admin');
$id = Pdb::lastInsertId();
$real_arr = Pdb::fetchRow(
    'name, password, type', 
    User::$table, 
    array('id=?' => $id));
test($real_arr, $ideal_arr, array('name' => 'Super Admin create Admin, db'));

// case 5 Admin update gold Price
begin_test();
$admin->updatePrice('PT950', '1903.21');
$admin->updatePrice('Au750', '1723.45');
test(1, 1, array('name' => 'Admin update gold Price'));

// case 6 Admin add Factory
begin_test();
$info = array(
    'name' => '嘉黛',
    'contact' => '吴小牛',
    'phone' => '13526523659',
    'qq' => '5833652584',
    'city' => '湖北武汉');
$admin->addFactory($info);
test(1, 1, array('name' => 'Admin add Factory'));

// case 7 Admin post Product, db
begin_test();
$info = array(
    'name' => '唯爱心形群镶女戒_test',
    'material' => json_encode(array(
        'PT950', 
        '18K白',
        '18K黄',
        '18K红')),
    'rabbet_start' => '0.30',
    'rabbet_end' => '0.60',
    'small_stone' => 3);
$product = $admin->postProduct($info);
test(
    Pdb::fetchRow('*', Product::$table, array('id=?' => $product->id)),
    $info,
    array(
        'name' => 'Admin post Product, db',
        'compare' => 'in'));

// case 8 Customer eidt Address
begin_test();
$address = $customer->defaultAddress();
$address->edit(array(
    'name' => '小池',
    'phone' => '14722320989',
    'detail' => '深圳罗湖区田贝'));
test(1, 1, array('name' => 'Customer eidt Address'));

// case 9 Customer add a Product to Cart
begin_test();
$old_entry_num = Pdb::count(Order::$table);
$opts = array(
    'material' => 'PT950',
    'size' => 12,
    'carve_text' => 'I love U');
$order = $customer->addProductToCart($product, $opts);
$entry_num = Pdb::count(Order::$table);
test(
    $old_entry_num + 1, 
    +$entry_num, 
    array('name' => 'Customer add a Product to Cart'));

// case 10 Cart count()
begin_test();
$cart = $customer->cart();
test(
    +$cart->count(),
    1,
    array('name' => 'Cart count()'));

// case 11 Customer del a Product from Cart
begin_test();
$opts = array(
    'material' => 'PT950',
    'size' => 12,
    'carve_text' => 'I love U');
$order = $customer->addProductToCart($product, $opts);
$order = $customer->addProductToCart($product, $opts); // add for twice
$old_num = $customer->cart()->count();
$customer->delProductFromCart($order);
$new_num = $customer->cart()->count();
test(
    $old_num - 1,
    $new_num,
    array('name' => 'Customer del a Product from Cart'));

// case 12 Customer submit a Cart
begin_test();
$old_entry_num = Pdb::count(BigOrder::$table);
$big_order = $customer->submitCart();
$entry_num = Pdb::count(BigOrder::$table);
test(
    $old_entry_num + 1,
    +$entry_num,
    array('name' => 'Customer submit a Cart'));

// case 13 Admin Confirmed Order (InFactory)
$admin->setOrderState($order, 'InFactory');
test(1, 1, array('name' => 'Admin Confirmed Order (InFactory)'));

// case 14 Admin add Customer
$info = array(
    'username' => 'user_ca_test',
    'password' => 'password',
    'realname' => '小三',
    'phone' => '1392910065',
    'qq' => '2981793048',
    'email' => 'cumt.xao@gmail.com',
    'address' => '北京某地',
    'remark' => '这个备注是干啥的？');
$admin->addCustomer($info);
test(1, 1, array('name' => 'Admin add Customer'));
