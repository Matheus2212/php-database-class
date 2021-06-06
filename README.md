# PHP Database Class

PHP Class that handles database Objects and Requests.

It's the result of quite some time of studying the PDO class and the new PHP 7.4 version, along with OO in PHP.

Let's go for the methods.

## addConnection

Method to define a database connection

## useConnection

This function will set which connection the class should use at the moment

## getTotalRequests

It will return the total amount number of requests that were done to the database

## performance

It will display a HTML comment on the page, displaying the total amount of time in unix the requisitions had taken

## fetch

Will fetch a row. If $simple then only one row and finishes.

## fetchAll

Will give all results

## count

Will give the number of rows inside the object

## empty

If the object is empty, returns true

## query

Performns a query

## pagedQuery

Breaks the number of results to be shown in a smallest number

## setLanguage

Define which language the class should work (default: english)

## setPaginationWords

Defines the words inside the pagination HTML when the page method is evoked

## getCurrentPage

Will return the current page according to URL

## page

Will give the pagination HTML

## date

Will give server date

## datetime

Will give server datetime

## setCollation

Will generate a SQL to change whole database collation. Returns a string, and can apply it too.

## setFriendlyURL

Will stablish the FriendlyURL instance

## prepare

Will prepare a SQL

## set

Will bind value to a prepared SQL

## formatMonney

Will make the value a monetary one

## insert

Will insert $data inside $table. If $additional is given, will transform values

## id

Returns last inserted id

## update

Updates $data in $table using given $rules. $additional is supported too.

## delete

Will delete whole table if $rules is omitted

## URLNormalize

Makes given string url friendly

## search

Simple search engine. It uses the content inside database (so, if database doesn't have much records...)
