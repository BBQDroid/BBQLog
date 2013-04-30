#!/bin/sh
wget $1 -O $2
mogrify -resize 100x $2
