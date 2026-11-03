## R1. params-updated fan-out per config: listener registered? / upload bytes / html bytes rendered back
| config | filter | listens | upload B | html B |
|---|---|---|---|---|
| counts=1 bounds=0 | checkbox | yes | 1207 | 5666 |
| counts=1 bounds=0 | checkbox-adv(500) | yes | 24185 | skipped |
| counts=1 bounds=0 | select | yes | 1026 | 1231 |
| counts=1 bounds=0 | radio | yes | 910 | 1538 |
| counts=1 bounds=0 | text | no | - | - |
| counts=1 bounds=0 | toggle | no | - | - |
| counts=1 bounds=0 | date | no | - | - |
| counts=1 bounds=0 | range | no | - | - |
| counts=1 bounds=0 | dual | no | - | - |
| counts=1 bounds=0 | TOTAL | | 27328 | 8435 |
| counts=0 bounds=1 | checkbox | no | - | - |
| counts=0 bounds=1 | checkbox-adv(500) | no | - | - |
| counts=0 bounds=1 | select | no | - | - |
| counts=0 bounds=1 | radio | no | - | - |
| counts=0 bounds=1 | text | no | - | - |
| counts=0 bounds=1 | toggle | no | - | - |
| counts=0 bounds=1 | date | no | - | - |
| counts=0 bounds=1 | range | yes | 1427 | 1206 |
| counts=0 bounds=1 | dual | yes | 1489 | 3205 |
| counts=0 bounds=1 | TOTAL | | 2916 | 4411 |
| counts=0 bounds=0 | checkbox | no | - | - |
| counts=0 bounds=0 | checkbox-adv(500) | no | - | - |
| counts=0 bounds=0 | select | no | - | - |
| counts=0 bounds=0 | radio | no | - | - |
| counts=0 bounds=0 | text | no | - | - |
| counts=0 bounds=0 | toggle | no | - | - |
| counts=0 bounds=0 | date | no | - | - |
| counts=0 bounds=0 | range | no | - | - |
| counts=0 bounds=0 | dual | no | - | - |
| counts=0 bounds=0 | TOTAL | | 0 | 0 |
| counts=1 bounds=1 | checkbox | yes | 1610 | 5666 |
| counts=1 bounds=1 | checkbox-adv(500) | yes | 24588 | skipped |
| counts=1 bounds=1 | select | yes | 1429 | 1231 |
| counts=1 bounds=1 | radio | yes | 1313 | 1538 |
| counts=1 bounds=1 | text | no | - | - |
| counts=1 bounds=1 | toggle | no | - | - |
| counts=1 bounds=1 | date | no | - | - |
| counts=1 bounds=1 | range | yes | 1427 | 1206 |
| counts=1 bounds=1 | dual | yes | 1489 | 3205 |
| counts=1 bounds=1 | TOTAL | | 31856 | 12846 |
## R2. html bytes rendered back per interaction (counts=1 bounds=1)
| component | interaction | response html B | response snapshot B | server ms |
|---|---|---|---|---|
| checkbox-adv(500) | params-updated (counts) | skipped | 19437 | 5.1 |
| checkbox-adv(500) | set selected | skipped | 20946 | 7.8 |
| checkbox-adv(500) | clear-all-filters | skipped | 20937 | 1.7 |
| checkbox | set selected | 5583 | 928 | 2.3 |
| checkbox | params-updated (counts) | 5662 | 899 | 2.9 |
| select | set selected | 1000 | 780 | 1.4 |
| select | params-updated (counts) | 1228 | 765 | 3.2 |
| radio | set selected | 1913 | 673 | 1.5 |
| radio | params-updated (counts) | 1536 | 664 | 1.5 |
| checkbox-plain(500) | set selected | 309851 | 20939 | 6.1 |
| checkbox-plain(500) | params-updated (counts) | 347851 | 19439 | 7.1 |
## D4. clear-option for a foreign field: html bytes rendered by the non-owning filter
| filter | rendered? | html B |
|---|---|---|
| checkbox | no | skipped |
| checkbox-adv(500) | no | skipped |
| select | no | skipped |
| radio | no | skipped |
| text | no | skipped |
| toggle | no | skipped |
| date | no | skipped |
| range | no | skipped |
| dual | no | skipped |
| TOTAL | | 0 |
