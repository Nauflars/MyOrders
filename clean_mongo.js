// MongoDB cleaning script
db = db.getSiblingDB('myorders_materials');
const result = db.material_view.deleteMany({});
print('Deleted ' + result.deletedCount + ' documents from material_view');
print('Remaining documents: ' + db.material_view.countDocuments());
