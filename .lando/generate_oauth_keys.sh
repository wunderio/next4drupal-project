#!/bin/bash

#
# Helper script to generate oath keys as part of the next4drupal setup.
#
# Usage:
# lando generate-oauth-keys
#

openssl genrsa -out private.key 2048
openssl rsa -in private.key -pubout > public.key
chmod 600 public.key
