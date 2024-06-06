# -*- coding: utf-8 -*-

import sys
from django.conf import settings
settings.configure()
from django.contrib.auth import hashers
raw_password = sys.argv[1]
try:
    salt = sys.argv[2]
except IndexError:
    salt = None
# hash = hashers.make_password(raw_password, salt=salt)
hash = hashers.make_password(raw_password, salt='salt')

sys.stdout.write("%s\n" % hash)
#sys.stdout.write("%s\n" % hashers.bcrypt(hash))
sys.stdout.flush()
sys.exit(0)
"""