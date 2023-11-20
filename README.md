# InWeb Payment MAIB driver

Module for MAIB payments manipulation

### Certificate generation

```bash
openssl pkcs12 -legacy -in certificate.pfx -nocerts -out key.pem
openssl pkcs12 -legacy -in certificate.pfx -nokeys -out cert.pem
```
