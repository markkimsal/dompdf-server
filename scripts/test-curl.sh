curl \
--request POST 'http://localhost:3001/convert/html' \
-H 'DomPdf-Version: 1.2.0' \
--form 'files=@"./tests/fixtures/html/svg.html"' $@
