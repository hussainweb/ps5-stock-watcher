# Stock Watcher for PS5

A simple PHP script based on [Amphp](https://github.com/amphp/amp) to
periodically check for PS5 stock.

## Why?

First, there was PS5. Then, there were scalpers and that meant that despite
repeated tries and following various Twitter accounts, I couldn't order one.
Then, I kept refreshing pages periodically, but I still missed couple of sales
that didn't get announced or were announced at the last minute. So, here's this
bot.

Next, I wanted to try out one of these async programming libraries for PHP and
this seemed like a good use case. I initially considered [ReactPHP](https://github.com/reactphp/reactphp)
but then after looking at differences, I went with Amphp which offered async
programming using coroutine style (promises seem so verbose in comparison).

So, with that decision and couple of hours one evening, I wrote this script.
Of course, I started seeing issues and kept modifying the script to counter
that. The git commit log is a good documentation of what has changed since I
wrote this.

## What does this do?

It only checks for stock availability on these sites for now.

- Walmart.ca
- BestBuy.ca
- EBGames.ca

It doesn't order automatically and I don't think I will add that. This script
just sends an email out but that is also very primitive (just using Monolog's
mail handler). In other words, it isn't fancy, but does the job.

Walmart.ca has also started implementing captcha functionality (I wish they had
done that earlier and I wouldn't have to write this). This script doesn't avoid
that. When that happens, I open the site manually and solve the captcha.
