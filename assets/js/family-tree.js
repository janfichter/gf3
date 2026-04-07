// assets/js/family-tree.js

(function() {
    'use strict';
    
    // Инициализируем все деревья на странице
    initializeAllFamilyTrees();
    
    function initializeAllFamilyTrees() {
        // Находим все контейнеры с классом f3 и data-root-id
        const familyTreeContainers = document.querySelectorAll('.f3[data-root-id]');
        
        if (familyTreeContainers.length === 0) {
            return;
        }
        
        familyTreeContainers.forEach(function(container) {
            initializeFamilyTree(container);
        });
    }
    
    function initializeFamilyTree(container) {
        if (!container) {
            console.warn('Контейнер древа не найден');
            return;
        }
        
        // ПОЛУЧАЕМ КОРНЕВОЙ ЭЛЕМЕНТ: сначала из data атрибута, потом из familyTreeAjax
        let rootId = container.dataset.rootId;
        let groupId = container.dataset.groupId || 0;
        
        // Если в data атрибуте нет, используем переданный из PHP
        if (!rootId && typeof familyTreeAjax !== 'undefined' && familyTreeAjax.root_member_id) {
            rootId = familyTreeAjax.root_member_id;
            console.log('Используем корневой элемент из настроек: ID ' + rootId);
        }
        
        if (!rootId) {
            console.error('Не указан корневой элемент древа');
            container.innerHTML = '<p style="text-align: center; padding: 50px; color: #666;">Не указан корневой элемент древа. Пожалуйста, установите корневой элемент в настройках.</p>';
            return;
        }
        
        loadFamilyTreeData(rootId, container, groupId);
    }
    
    function loadFamilyTreeData(rootId, container, groupId) {
        // Проверяем наличие необходимых объектов
        if (typeof familyTreeAjax === 'undefined' || !familyTreeAjax.ajaxurl || !familyTreeAjax.nonce) {
            console.error('Ошибка конфигурации AJAX');
            container.innerHTML = '<p style="text-align: center; padding: 50px; color: #666;">Ошибка конфигурации AJAX</p>';
            return;
        }
        
        // Показываем индикатор загрузки
        container.innerHTML = '<div style="text-align: center; padding: 50px;"><div class="spinner"></div><p>Загрузка семейного древа...</p></div>';
        
        const formData = new FormData();
        formData.append('action', 'get_family_tree_data');
        formData.append('root_id', rootId);
        formData.append('group_id', groupId);
        formData.append('nonce', familyTreeAjax.nonce);
        
        fetch(familyTreeAjax.ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data && data.nodes && data.nodes.length > 0) {
                if (data.links) {
                    renderFamilyTreeWithLibrary(data, container);
                } else {
                    container.innerHTML = '<p style="text-align: center; padding: 50px; color: #666;">Данные загружены, но связи не найдены</p>';
                }
            } else {
                container.innerHTML = '<p style="text-align: center; padding: 50px; color: #666;">Нет данных для отображения</p>';
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки данных:', error);
            container.innerHTML = '<p style="text-align: center; padding: 50px; color: #666;">Ошибка загрузки данных: ' + error.message + '</p>';
        });
    }
    
    function renderFamilyTreeWithLibrary(data, container) {
        try {
            // Проверяем наличие библиотек
            if (typeof d3 === 'undefined') {
                console.error('Библиотека d3 не загружена');
                container.innerHTML = '<p style="text-align: center; padding: 50px; color: #666;">Библиотека d3 не загружена</p>';
                return;
            }
            
            if (typeof f3 === 'undefined') {
                console.error('Библиотека f3 (Family Chart) не загружена');
                container.innerHTML = '<p style="text-align: center; padding: 50px; color: #666;">Библиотека Family Chart не загружена</p>';
                return;
            }
            
            // Очищаем контейнер
            container.innerHTML = '';
            
            // Преобразуем данные в формат f3
            const f3Data = convertDataToF3Format(data);
            
            // Инициализируем библиотеку f3
            const store = f3.createStore({
                data: f3Data,
                node_separation: 250,
                level_separation: 150
            });
            
            const view = f3.d3AnimationView({
                store: store,
                cont: container
            });
            
            // Получаем SVG элемент, созданный f3
            const svgElement = container.querySelector('svg');
            if (svgElement) {
                // Устанавливаем ширину 100% и высоту 600px
                svgElement.setAttribute('width', '100%');
                svgElement.setAttribute('height', '600');
            }

            const Card = f3.elements.Card({
                store: store,
                svg: view.svg,
                card_dim: {
                    w: 220,
                    h: 100,
                    text_x: 80,
                    text_y: 15,
                    img_w: 60,
                    img_h: 60,
                    img_x: 10,
                    img_y: 20
                },
                card_display: [
                    (d) => {
                        // Имя - отображаем как есть
                        const firstName = d.data["first name"] || "";
                        return firstName || "Неизвестно";
                    },
                    (d) => {
                        // Отчество - отображаем как есть
                        return d.data["middle name"] || "";
                    },
                    (d) => {
                        // Фамилия с девичьей фамилией в скобках (если есть)
                        const lastName = d.data["last name"] || "";
                        const maidenName = d.data["maiden name"] || "";
                        
                        if (maidenName) {
                            if (lastName) {
                                return `${lastName} (${maidenName})`;
                            } else {
                                return maidenName;
                            }
                        }
                        return lastName;
                    },
                    (d) => {
                        // Пустой элемент, чтобы не создавать лишнюю строку
                        return "";
                    },
                    (d) => {
                        const birthday = d.data["birthday"] || "";
                        const death = d.data["death"] || "";
                        
                        // Если человек умер (в death уже содержится "Умер" или "Умерла")
                        if (death === "Умер" || death === "Умерла") {
                            if (birthday) {
                                return `${birthday} - ${death}`;
                            } else {
                                return `? - ${death}`;
                            }
                        }
                        // Если есть дата смерти (числовая)
                        else if (death) {
                            if (birthday) {
                                return `${birthday} - ${death}`;
                            } else {
                                return `? - ${death}`;
                            }
                        }
                        // Если есть только дата рождения
                        else if (birthday) {
                            return birthday;
                        }
                        // Если ничего не указано
                        else {
                            return "";
                        }
                    }
                ],
                mini_tree: true,
                link_break: true
            });

            view.setCard(Card);
            
            // После отрисовки дерева добавляем иконки
            store.setOnUpdate((props) => {
                view.update(props || {});
                // Добавляем иконки после каждого обновления
                setTimeout(() => {
                    addLinkIconsToCards(data.nodes, container);
                    // Переназначаем обработчики после каждого обновления
                    attachClickHandlers(container, store);
                }, 50);
            });
            
            // Обновляем дерево
            store.update.tree({ initial: true });

            // Центрирование на узле
            const centerOnNodeId = container.dataset.centerOn;
            if (centerOnNodeId && typeof store.centerOnNode === 'function') {
                const attemptCenter = () => {
                    if (store.getNodeById && store.getNodeById(centerOnNodeId)) {
                        try {
                            store.centerOnNode(centerOnNodeId);
                        } catch (e) {
                            console.warn('Не удалось выполнить centerOnNode для ID:', centerOnNodeId);
                        }
                    } else {
                        setTimeout(attemptCenter, 200);
                    }
                };
                setTimeout(attemptCenter, 500);
            }
            
            // Добавляем зум
            setTimeout(() => {
                const zoomBehavior = d3.zoom()
                    .scaleExtent([0.5, 2.0])
                    .on('zoom', function(event) {
                        d3.select(container).select('svg').select('g.view').attr('transform', event.transform);
                    });
                
                d3.select(container).select('svg').call(zoomBehavior);
            }, 100);
            
            console.log('Дерево создано успешно!');
            
        } catch (error) {
            console.error('Ошибка отображения древа:', error);
            container.innerHTML = '<p style="text-align: center; padding: 50px; color: #666;">Ошибка отображения древа: ' + error.message + '</p>';
        }
    }
    
    // Обработчик кликов с анимацией
    function attachClickHandlers(container, store) {
        container.querySelectorAll('.card').forEach(card => {
            card.addEventListener('click', function(event) {
                // Предотвращаем стандартное поведение f3 (если оно есть)
                event.stopPropagation();
                const nodeId = this.getAttribute('data-id');
                if (nodeId) {
                    // Обновляем дерево с анимацией
                    store.update.tree({ id: nodeId, animated: true });
                }
            });
        });
    }
    
    // Функция для добавления иконок ссылок к карточкам
    function addLinkIconsToCards(nodesData, container) {
        try {
            // Проходим по всем узлам данных
            nodesData.forEach(node => {
                const personId = node.id;
                const personUrl = node.permalink;
                
                if (personId && personUrl) {
                    // Находим соответствующую карточку в конкретном контейнере
                    const cardElement = container.querySelector(`.card[data-id="${personId}"]`);
                    
                    if (cardElement) {
                        // Проверяем, не добавлена ли уже иконка
                        const existingIcon = cardElement.querySelector('.person-link-icon-container');
                        if (!existingIcon) {
                            try {
                                // Находим card-body внутри карточки
                                const cardBody = cardElement.querySelector('.card-body');
                                if (cardBody) {
                                    // Создаем контейнер для иконки
                                    const iconContainer = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                                    iconContainer.setAttribute('class', 'person-link-icon-container');
                                    // Позиционируем в правом верхнем углу (с небольшим отступом)
                                    iconContainer.setAttribute('transform', 'translate(195, 5)'); // 220(ширина) - 25(размер иконки) = 195
                                    iconContainer.style.cursor = 'pointer';
                                    
                                    // Добавляем обработчик клика
                                    iconContainer.addEventListener('click', function(event) {
                                        event.stopPropagation();
                                        window.open(personUrl, '_blank');
                                    });
                                    
                                    // Создаем белый круг с серой обводкой
                                    const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                                    circle.setAttribute('cx', '10');
                                    circle.setAttribute('cy', '10');
                                    circle.setAttribute('r', '9');
                                    circle.setAttribute('fill', 'white');
                                    circle.setAttribute('stroke', '#ccc');
                                    circle.setAttribute('stroke-width', '1');
                                    
                                    // Создаем упрощенную иконку лупы (простой путь)
                                    const iconPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                                    // Этот путь представляет собой упрощенную иконку поиска
                                    iconPath.setAttribute('d', 'M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z');
                                    iconPath.setAttribute('fill', '#000000');
                                    // Уменьшаем масштаб до 0.5 и смещаем внутрь круга, чтобы создать больший отступ
                                    iconPath.setAttribute('transform', 'scale(0.5) translate(10, 10)');
                                    
                                    // Создаем title (tooltip)
                                    const titleElement = document.createElementNS('http://www.w3.org/2000/svg', 'title');
                                    titleElement.textContent = 'Перейти к странице персоны';
                                    
                                    // Собираем элементы
                                    iconContainer.appendChild(circle);
                                    iconContainer.appendChild(iconPath);
                                    iconContainer.appendChild(titleElement);
                                    
                                    // Добавляем иконку в card-body
                                    cardBody.appendChild(iconContainer);
                                }
                            } catch (e) {
                                console.warn('Ошибка при добавлении иконки для персоны ID:', personId, e);
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Ошибка в addLinkIconsToCards:', error);
        }
    }
    
    function convertDataToF3Format(data) {
        const f3Nodes = [];
        
        // Преобразуем узлы
        data.nodes.forEach(node => {
            // Используем поля напрямую, как они передаются из PHP
            const firstName = node.firstName || "";
            const middleName = node.middleName || "";
            const lastName = node.lastName || "";
            const maidenName = node.maidenName || "";
            
            const f3Node = {
                id: String(node.id),
                rels: {},
                data: {
                    "first name": firstName,
                    "last name": lastName,
                    "middle name": middleName,
                    "maiden name": maidenName,
                    "birthday": node.birthDate || "",
                    "death": node.deathDate || "",
                    "avatar": node.img || "",
                    "gender": node.gender === 'female' ? 'F' : 'M',
                    // Добавляем permalink
                    "permalink": node.permalink || ""
                }
            };
            
            f3Nodes.push(f3Node);
        });
        
        // Добавляем связи
        data.links.forEach(link => {
            const sourceNode = f3Nodes.find(node => node.id === String(link.source));
            const targetNode = f3Nodes.find(node => node.id === String(link.target));
            
            if (sourceNode && targetNode) {
                if (link.type === 'child') {
                    // Для детей указываем родителей
                    if (sourceNode.data.gender === 'M') {
                        if (!targetNode.rels.father) {
                            targetNode.rels.father = sourceNode.id;
                        }
                    } else if (sourceNode.data.gender === 'F') {
                        if (!targetNode.rels.mother) {
                            targetNode.rels.mother = sourceNode.id;
                        }
                    }
                    
                    // Добавляем ребенка к родителю
                    if (!sourceNode.rels.children) sourceNode.rels.children = [];
                    if (!sourceNode.rels.children.includes(targetNode.id)) {
                        sourceNode.rels.children.push(targetNode.id);
                    }
                } else if (link.type === 'spouse') {
                    if (!sourceNode.rels.spouses) sourceNode.rels.spouses = [];
                    if (!sourceNode.rels.spouses.includes(targetNode.id)) {
                        sourceNode.rels.spouses.push(targetNode.id);
                    }
                    
                    if (!targetNode.rels.spouses) targetNode.rels.spouses = [];
                    if (!targetNode.rels.spouses.includes(sourceNode.id)) {
                        targetNode.rels.spouses.push(sourceNode.id);
                    }
                }
            }
        });
        
        return f3Nodes;
    }
    
    // Инициализация для страниц с древом
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            if (isFamilyTreePage()) {
                initializeFamilyTree();
            }
        });
    } else {
        if (isFamilyTreePage()) {
            initializeFamilyTree();
        }
    }
    
    window.addEventListener('load', function() {
        if (isFamilyTreePage() && document.getElementById('FamilyChart')) {
            setTimeout(initializeFamilyTree, 300);
        }
    });
    
    // Функция проверки страницы
    function isFamilyTreePage() {
        return document.getElementById('FamilyChart') !== null;
    }
})();