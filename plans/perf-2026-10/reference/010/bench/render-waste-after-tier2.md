## R1. params-updated fan-out per config: listener registered? / upload bytes / html bytes rendered back
| config | filter | listens | upload B | html B |
|---|---|---|---|---|
| counts=1 bounds=0 | checkbox | yes | 1207 | 6180 |
| counts=1 bounds=0 | checkbox-adv(500) | yes | 24185 | skipped |
| counts=1 bounds=0 | select | yes | 1026 | 1231 |
| counts=1 bounds=0 | radio | yes | 910 | 2048 |
| counts=1 bounds=0 | text | no | - | - |
| counts=1 bounds=0 | toggle | no | - | - |
| counts=1 bounds=0 | date | no | - | - |
| counts=1 bounds=0 | range | yes | 1024 | skipped |
| counts=1 bounds=0 | dual | yes | 1086 | skipped |
| counts=1 bounds=0 | TOTAL | | 29438 | 9459 |
| counts=0 bounds=1 | checkbox | yes | 1610 | skipped |
| counts=0 bounds=1 | checkbox-adv(500) | yes | 24588 | skipped |
| counts=0 bounds=1 | select | yes | 1429 | skipped |
| counts=0 bounds=1 | radio | yes | 1313 | skipped |
| counts=0 bounds=1 | text | no | - | - |
| counts=0 bounds=1 | toggle | no | - | - |
| counts=0 bounds=1 | date | no | - | - |
| counts=0 bounds=1 | range | yes | 1427 | 1206 |
| counts=0 bounds=1 | dual | yes | 1489 | 3205 |
| counts=0 bounds=1 | TOTAL | | 31856 | 4411 |
| counts=0 bounds=0 | checkbox | yes | 1207 | skipped |
| counts=0 bounds=0 | checkbox-adv(500) | yes | 24185 | skipped |
| counts=0 bounds=0 | select | yes | 1026 | skipped |
| counts=0 bounds=0 | radio | yes | 910 | skipped |
| counts=0 bounds=0 | text | no | - | - |
| counts=0 bounds=0 | toggle | no | - | - |
| counts=0 bounds=0 | date | no | - | - |
| counts=0 bounds=0 | range | yes | 1024 | skipped |
| counts=0 bounds=0 | dual | yes | 1086 | skipped |
| counts=0 bounds=0 | TOTAL | | 29438 | 0 |
| counts=1 bounds=1 | checkbox | yes | 1610 | 6180 |
| counts=1 bounds=1 | checkbox-adv(500) | yes | 24588 | skipped |
| counts=1 bounds=1 | select | yes | 1429 | 1231 |
| counts=1 bounds=1 | radio | yes | 1313 | 2048 |
| counts=1 bounds=1 | text | no | - | - |
| counts=1 bounds=1 | toggle | no | - | - |
| counts=1 bounds=1 | date | no | - | - |
| counts=1 bounds=1 | range | yes | 1427 | 1206 |
| counts=1 bounds=1 | dual | yes | 1489 | 3205 |
| counts=1 bounds=1 | TOTAL | | 31856 | 13870 |
## R2. html bytes rendered back per interaction (counts=1 bounds=1)
| component | interaction | response html B | response snapshot B | server ms |
|---|---|---|---|---|
| checkbox-adv(500) | params-updated (counts) | skipped | 19437 | 4.9 |
| checkbox-adv(500) | set selected | skipped | 20946 | 11.9 |
| checkbox-adv(500) | clear-all-filters | skipped | 20937 | 1.6 |
| checkbox | set selected | skipped | 928 | 1.2 |
| checkbox | params-updated (counts) | 6176 | 899 | 2.8 |
| select | set selected | skipped | 780 | 1.4 |
| select | params-updated (counts) | 1228 | 765 | 3.1 |
| radio | set selected | skipped | 673 | 1.3 |
| radio | params-updated (counts) | 2046 | 664 | 1.6 |
| checkbox-plain(500) | set selected | skipped | 20939 | 2.7 |
| checkbox-plain(500) | params-updated (counts) | 347836 | 19439 | 6.8 |
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
