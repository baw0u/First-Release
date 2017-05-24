## Simple PDO Database Abstraction for PHP
### Manipulate your data using simple methods

created: 05/24/2017  
latest update: 05/24/2017  
by: Vincent HERBAUT  
email: [contact@herbaut-vincent.com](mailto:contact@herbaut-vincent.com)  
version: 0.1   

Powerful actions in simple methods. No SQL code writing.  

### Documentation

Click [here](http://www.herbaut-vincent.com/documentation/spda/) to see the documentation

### Feature

The script allows you to organize the work with database with a simple methods and without writing queries manually.  
* Simple insert, update and delete 
* Batch inserts and updates  
* Where conditions  
* Sorting and grouping  
* Joins of multiple tables  
* Concatenation and group concatenation  
* Min, max, sum and total 

### Instalation

**Download with Composer**
```
composer require baw0u/spda
```
**Or upload and connect**  
Upload **spda** on your server  
Open **spda.config.php** and configure your database connexion  
```
public static $dbNname = 'dbName'; // Your database name
public static $dbUser = 'dbUser'; // Your database username
public static $dbPass = 'dbPass'; // // Your database password
public static $dbHost = 'localhost'; // Your database host, 'localhost' is default
public static $dbEncoding = 'utf8'; // Your database encoding
public static $autoReset = true; // Resets the conditions after the query
public static $debug = true; // Display SQL Request in errors and show_request() method
```
Include spda.class.php in your page  
```
require("spda/spda.class.php");
use baw0u\spda;
```  
**First request**  
Create object of your table, for example - table posts. Lets retrieve all rows from 'posts' and write in $results variable.  
```
$posts = spda::table("posts");
$results = $bdd->get();
``` 
It's really easy.  
You can use **show_request()** method to see the request  
```
echo $posts->show_request(); // Show the SQL request
 
/* Result */
SELECT `posts`.`idposts`, `posts`.`online`, `posts`.`name`, `posts`.`content`, `posts`.`category_id`, `posts`.`updated`, `posts`.`created` FROM `posts`
``` 
And complete code  
```
require("spda/spda.class.php");
use baw0u\spda;
$posts = spda::table("posts");
$results = $bdd->get();
```
Very simple, right?   

### System Requirements  

* PHP5  
* MySQL5  
* mysqli extention  
* PDO  

### Codes samples  
Example : get all posts  
```
$posts = spda::table("posts");
$results = $bdd->get(); // list of arrays
``` 
Example : get ** 1 post **   
```
$posts = spda::table("posts");
$result = $posts->get_one(); // post info as array
``` 
Example : some data manipulation  
```
$posts = spda::table("posts");
$posts->fields("online,name,content,category_id"); // select specific fields
$posts->where("category_id",1); // get posts where category_id = 1
$posts->and_where("online",1); // get posts where online = 1
$post = $posts->get_one();
 
$new_post = array('online'=>'1','name'=>'My new post','content'=>'Some contents','category_id'=>1); // create new post
$posts->insert($new_post); // insert new post to database
``` 

## AUTHOR

**Vincent Herbaut**
* [http://www.herbaut-vincent.com](http://www.herbaut-vincent.com)